<?php

namespace App\Http\Controllers;

use App\Models\QueueTicket;
use App\Models\Restaurant;
use App\Services\QueueService;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;

class RestaurantController extends Controller
{
    public function show(Request $request, Restaurant $restaurant, QueueService $queueService)
    {
        $snapshot = $queueService->getRestaurantSnapshot($restaurant);
        $whatsappUrl = app(WhatsAppService::class)->entryUrl($restaurant);
        $webSignup = (bool) config('satset.web_signup');
        $myTicket = $webSignup ? $this->ticketFromSession($request, $restaurant) : null;

        return view('restaurants.show', compact('restaurant', 'snapshot', 'whatsappUrl', 'webSignup', 'myTicket'));
    }

    /**
     * Web sign-up: the customer picks a party size on the outlet page and gets a ticket.
     */
    public function join(Request $request, Restaurant $restaurant, QueueService $queueService)
    {
        abort_unless(config('satset.web_signup'), 404);

        // One active ticket per browser per outlet, so refreshes or double taps do not queue twice.
        if ($existing = $this->ticketFromSession($request, $restaurant)) {
            return redirect()->route('queue.show', $existing->public_token)
                ->with('status', 'Anda sudah memiliki antrean aktif di outlet ini.');
        }

        $validated = $request->validate([
            'party_size' => ['required', 'integer', 'min:1', 'max:'.$restaurant->max_party_size],
        ], [
            'party_size.*' => "Jumlah tamu harus 1 sampai {$restaurant->max_party_size} orang.",
        ]);

        $ticket = $queueService->createTicket($restaurant, partySize: (int) $validated['party_size']);
        $request->session()->put($this->sessionKey($restaurant), $ticket->public_token);

        return redirect()->route('queue.show', $ticket->public_token)
            ->with('status', "Berhasil! Nomor antrean Anda {$ticket->queue_number}. Simpan halaman ini.");
    }

    public function whatsapp(Restaurant $restaurant, WhatsAppService $whatsapp)
    {
        abort_unless($restaurant->active && $restaurant->queue_open, 409, 'Antrean outlet sedang ditutup.');
        $url = $whatsapp->entryUrl($restaurant);
        abort_unless($url, 503, 'Pendaftaran WhatsApp belum tersedia. Silakan hubungi staf outlet.');

        return redirect()->away($url);
    }

    public function status(Request $request, Restaurant $restaurant, QueueService $queueService)
    {
        // Background polling must not consume a flash message meant for the next page.
        $request->session()->reflash();

        return response()->json($queueService->getRestaurantSnapshot($restaurant))->header('Cache-Control', 'no-store');
    }

    private function ticketFromSession(Request $request, Restaurant $restaurant): ?QueueTicket
    {
        $token = $request->session()->get($this->sessionKey($restaurant));
        $ticket = $token ? QueueTicket::query()->where('public_token', $token)->where('restaurant_id', $restaurant->id)->first() : null;

        if ($token && ! $ticket?->isActive()) {
            $request->session()->forget($this->sessionKey($restaurant));

            return null;
        }

        return $ticket;
    }

    private function sessionKey(Restaurant $restaurant): string
    {
        return 'satset.web_tickets.'.$restaurant->id;
    }
}
