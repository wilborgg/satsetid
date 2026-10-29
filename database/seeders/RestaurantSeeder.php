<?php

namespace Database\Seeders;

use App\Models\QueueTicket;
use App\Models\Restaurant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RestaurantSeeder extends Seeder
{
    public function run(): void
    {
        $restaurants = [
            [
                'name' => 'Ayam Bakar Nusantara',
                'category' => 'Ayam',
                'description' => 'Ayam bakar bumbu rempah dengan nasi hangat dan sambal khas.',
                'image' => 'images/restaurants/ayam-bakar.jpg',
                'prefix' => 'A',
                'average_service_minutes' => 5,
                'sample_queue' => 10,
            ],
            [
                'name' => 'Ayam Geprek Mantap',
                'category' => 'Ayam',
                'description' => 'Ayam geprek pedas dengan pilihan level sambal.',
                'image' => 'images/restaurants/ayam-geprek.jpg',
                'prefix' => 'B',
                'average_service_minutes' => 4,
                'sample_queue' => 3,
            ],
            [
                'name' => 'Rice Bowl & More',
                'category' => 'Rice Bowl',
                'description' => 'Rice bowl cepat saji dengan topping ayam, telur, dan sayuran.',
                'image' => 'images/restaurants/rice-bowl.jpg',
                'prefix' => 'C',
                'average_service_minutes' => 4,
                'sample_queue' => 2,
            ],
        ];

        foreach ($restaurants as $data) {
            $sampleQueue = $data['sample_queue'];
            unset($data['sample_queue']);

            $restaurant = Restaurant::query()->firstOrCreate(
                ['prefix' => $data['prefix']],
                [
                    ...$data,
                    'slug' => Str::slug($data['name']).'-'.Str::lower($data['prefix']),
                    'max_wait_minutes' => 30,
                    'active' => true,
                ]
            );

            if (app()->environment('local', 'testing') && $restaurant->queueTickets()->count() === 0) {
                for ($i = 1; $i <= $sampleQueue; $i++) {
                    QueueTicket::query()->create([
                        // Seeders run without model events, so the token is not generated automatically.
                        'public_token' => (string) Str::uuid(),
                        'restaurant_id' => $restaurant->id,
                        'customer_name' => "Sample Customer {$i}",
                        'phone_number' => '6281234500'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                        'queue_number' => sprintf('%s-%03d', $restaurant->prefix, $i),
                        'sequence_number' => $i,
                        'status' => QueueTicket::STATUS_WAITING,
                        'joined_at' => now()->subMinutes(($sampleQueue - $i + 1) * 3),
                    ]);
                }
            }
        }
    }
}
