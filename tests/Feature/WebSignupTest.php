<?php

namespace Tests\Feature;

use App\Models\QueueTicket;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebSignupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['satset.web_signup' => true]);
    }

    public function test_disabled_by_default(): void
    {
        config(['satset.web_signup' => false]);
        $restaurant = $this->restaurant();

        $this->get(route('outlets.show', $restaurant->slug))->assertOk()->assertDontSee('name="party_size"', false);
        $this->post(route('outlets.join', $restaurant->slug), ['party_size' => 2])->assertNotFound();
        $this->assertDatabaseCount('queue_tickets', 0);
    }

    public function test_opening_the_outlet_page_never_creates_a_ticket(): void
    {
        $restaurant = $this->restaurant();

        $this->get(route('outlets.show', $restaurant->slug))->assertOk()->assertSee('Ambil nomor antrean');
        $this->assertDatabaseCount('queue_tickets', 0);
    }

    public function test_customer_takes_a_number_from_the_outlet_page(): void
    {
        $restaurant = $this->restaurant();

        $response = $this->post(route('outlets.join', $restaurant->slug), ['party_size' => 3]);

        $ticket = QueueTicket::query()->firstOrFail();
        $response->assertRedirect(route('queue.show', $ticket->public_token));
        $this->assertSame('A-001', $ticket->queue_number);
        $this->assertSame(3, $ticket->party_size);
        $this->assertNull($ticket->phone_number);
        $this->get(route('queue.show', $ticket->public_token))->assertOk()->assertSee('A-001')->assertSee('bookmark halaman ini');
    }

    public function test_same_browser_is_sent_back_to_its_active_ticket(): void
    {
        $restaurant = $this->restaurant();
        $this->post(route('outlets.join', $restaurant->slug), ['party_size' => 2]);
        $ticket = QueueTicket::query()->firstOrFail();

        $this->post(route('outlets.join', $restaurant->slug), ['party_size' => 2])
            ->assertRedirect(route('queue.show', $ticket->public_token));
        $this->assertDatabaseCount('queue_tickets', 1);
        $this->get(route('outlets.show', $restaurant->slug))->assertSee('Lihat tiket saya')->assertDontSee('name="party_size"', false);

        // Once the ticket ends, the browser may queue again.
        $ticket->update(['status' => QueueTicket::STATUS_COMPLETED]);
        $this->post(route('outlets.join', $restaurant->slug), ['party_size' => 2]);
        $this->assertDatabaseCount('queue_tickets', 2);
    }

    public function test_background_polling_does_not_swallow_the_success_message(): void
    {
        $restaurant = $this->restaurant();
        $this->post(route('outlets.join', $restaurant->slug), ['party_size' => 2]);
        $ticket = QueueTicket::query()->firstOrFail();

        // The outlet page that was just left polls once more before the browser follows the redirect.
        $this->getJson(route('outlets.status', $restaurant->slug))->assertOk();
        $this->get(route('queue.show', $ticket->public_token))->assertSee('Berhasil! Nomor antrean Anda A-001');
    }

    public function test_party_size_is_validated_against_the_outlet_limit(): void
    {
        $restaurant = $this->restaurant(['max_party_size' => 4]);

        foreach (['0', '5', 'abc', ''] as $size) {
            $this->post(route('outlets.join', $restaurant->slug), ['party_size' => $size])->assertSessionHasErrors('party_size');
        }
        $this->assertDatabaseCount('queue_tickets', 0);
    }

    public function test_closed_outlet_rejects_sign_up(): void
    {
        $restaurant = $this->restaurant(['queue_open' => false]);

        $this->get(route('outlets.show', $restaurant->slug))->assertOk()->assertSee('Antrean sedang ditutup');
        $this->post(route('outlets.join', $restaurant->slug), ['party_size' => 2])->assertSessionHasErrors('restaurant');
        $this->assertDatabaseCount('queue_tickets', 0);
    }

    private function restaurant(array $overrides = []): Restaurant
    {
        return Restaurant::query()->create(array_merge([
            'name' => 'Ayam Bakar Nusantara', 'slug' => 'ayam-bakar', 'category' => 'Ayam', 'prefix' => 'A',
            'average_service_minutes' => 5, 'max_wait_minutes' => 30, 'max_party_size' => 6, 'active' => true, 'queue_open' => true,
        ], $overrides));
    }
}
