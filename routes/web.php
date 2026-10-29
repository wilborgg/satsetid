<?php

use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminQueueController;
use App\Http\Controllers\Admin\AdminQueueOverviewController;
use App\Http\Controllers\Admin\AdminRestaurantController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\TwilioWebhookController;
use App\Http\Controllers\WhatsAppWebhookController;
use App\Http\Controllers\Admin\AdminWhatsAppLogController;
use App\Http\Controllers\Admin\AdminQrController;
use App\Http\Controllers\QueueController;
use App\Http\Controllers\RestaurantController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('/r/{restaurant:slug}', [RestaurantController::class, 'show'])->name('outlets.show');
Route::post('/r/{restaurant:slug}/queue', [RestaurantController::class, 'join'])->middleware('throttle:6,1')->name('outlets.join');
Route::get('/r/{restaurant:slug}/whatsapp', [RestaurantController::class, 'whatsapp'])->middleware('throttle:30,1')->name('outlets.whatsapp');
Route::get('/r/{restaurant:slug}/status', [RestaurantController::class, 'status'])->middleware('throttle:120,1')->name('outlets.status');

Route::get('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify'])->name('whatsapp.verify');
Route::post('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'receive'])->name('whatsapp.webhook');

Route::post('/webhooks/twilio', [TwilioWebhookController::class, 'receive'])->name('whatsapp.twilio');
Route::post('/webhooks/twilio/status', [TwilioWebhookController::class, 'status'])->name('whatsapp.twilio.status');

Route::get('/restaurants/{restaurant}', fn (\App\Models\Restaurant $restaurant) => redirect()->route('outlets.show', $restaurant->slug))
    ->name('restaurants.show');

Route::get('/restaurants/{restaurant}/queue/new', fn (\App\Models\Restaurant $restaurant) => redirect()->route('outlets.show', $restaurant->slug))
    ->name('queue.create');

Route::get('/queue/{ticket:public_token}', [QueueController::class, 'show'])
    ->name('queue.show');

Route::get('/queue/{ticket:public_token}/status', [QueueController::class, 'status'])
    ->name('queue.status');

Route::post('/queue/{ticket:public_token}/cancel', [QueueController::class, 'cancel'])
    ->name('queue.cancel');

Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/login', [AdminAuthController::class, 'showLogin'])
        ->name('login');

    Route::post('/login', [AdminAuthController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('login.store');

    Route::middleware(['auth', 'admin.active'])->group(function (): void {
        Route::get('/', fn () => redirect()->route('admin.dashboard'))
            ->name('home');

        Route::post('/logout', [AdminAuthController::class, 'logout'])
            ->name('logout');

        // Every admin. Lists are scoped to the outlets the account may manage.
        Route::get('/dashboard', AdminDashboardController::class)
            ->name('dashboard');

        Route::get('/queues', AdminQueueOverviewController::class)
            ->name('queues.index');
        Route::get('/qr', [AdminQrController::class, 'index'])->name('qr.index');

        // Outlet-scoped: owners, or outlet admins assigned to {restaurant}.
        Route::middleware('can:manage-restaurant,restaurant')->group(function (): void {
            Route::get('/restaurants/{restaurant}/qr', [AdminQrController::class, 'show'])->name('qr.show');
            Route::get('/restaurants/{restaurant}/qr.svg', [AdminQrController::class, 'image'])->name('qr.image');

            Route::get('/restaurants/{restaurant}/edit', [AdminRestaurantController::class, 'edit'])
                ->name('restaurants.edit');
            Route::put('/restaurants/{restaurant}', [AdminRestaurantController::class, 'update'])
                ->name('restaurants.update');

            Route::get('/restaurants/{restaurant}/queue', [AdminQueueController::class, 'show'])
                ->name('restaurants.queue');

            Route::post('/restaurants/{restaurant}/queue/call-next', [AdminQueueController::class, 'callNext'])
                ->name('restaurants.queue.call-next');

            Route::post('/restaurants/{restaurant}/queue/complete', [AdminQueueController::class, 'complete'])
                ->name('restaurants.queue.complete');

            Route::post('/restaurants/{restaurant}/queue/skip', [AdminQueueController::class, 'skip'])
                ->name('restaurants.queue.skip');

            Route::post('/restaurants/{restaurant}/queue/{ticket}/cancel', [AdminQueueController::class, 'cancel'])
                ->name('restaurants.queue.cancel');
        });

        // SatSet owner only.
        Route::middleware('can:owner')->group(function (): void {
            Route::resource('/restaurants', AdminRestaurantController::class)
                ->only(['index', 'create', 'store', 'destroy']);

            Route::resource('/users', AdminUserController::class)
                ->except(['show', 'destroy']);

            Route::get('/whatsapp-logs', AdminWhatsAppLogController::class)->name('whatsapp-logs.index');
        });
    });
});
