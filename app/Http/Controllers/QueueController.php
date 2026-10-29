<?php

namespace App\Http\Controllers;

use App\Models\QueueTicket;
use App\Models\Restaurant;
use App\Services\QueueService;
use Illuminate\Http\Request;

class QueueController extends Controller
{
    public function show(QueueTicket $ticket, QueueService $queueService)
    {
        $ticket->load('restaurant');
        $position = $queueService->calculateCustomerPosition($ticket);
        $estimatedWait = $queueService->calculateEstimatedWait($ticket->restaurant, $ticket);

        return response()->view('queue.show', compact('ticket', 'position', 'estimatedWait'))
            ->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function status(Request $request, QueueTicket $ticket, QueueService $queueService)
    {
        // Background polling must not consume a flash message meant for the next page.
        $request->session()->reflash();
        $ticket->load('restaurant');
        $position = $queueService->calculateCustomerPosition($ticket);

        return response()->json([
            'status' => $ticket->status,
            'position' => $position,
            'people_before' => max($position - 1, 0),
            'estimated_wait' => $queueService->calculateEstimatedWait($ticket->restaurant, $ticket),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function cancel(Request $request, QueueTicket $ticket, QueueService $queueService)
    {
        $queueService->cancelTicket($ticket);

        return redirect()->route('queue.show', ['ticket' => $ticket->public_token])->with('status', 'Antrean berhasil dibatalkan.');
    }
}
