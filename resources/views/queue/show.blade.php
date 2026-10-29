@php
    $messages = [
        'waiting' => $ticket->phone_number
            ? 'Tunggu dengan nyaman. Pembaruan antrean dikirim melalui WhatsApp.'
            : 'Tunggu dengan nyaman. Simpan atau bookmark halaman ini, karena posisi antrean diperbarui di sini secara otomatis.',
        'called' => 'Giliran Anda sudah tiba. Silakan menuju staf outlet.',
        'ended' => 'Antrean ini sudah berakhir. Terima kasih sudah menggunakan SatSet.',
    ];
@endphp
<x-layouts.customer :title="$ticket->queue_number.' | SatSet'">
    <section class="page" data-status-url="{{ route('queue.status', $ticket->public_token) }}" data-ticket data-status="{{ $ticket->status }}">
        <a class="back-link" href="{{ route('outlets.show', $ticket->restaurant->slug) }}">&larr; {{ $ticket->restaurant->name }}</a>

        <div class="ticket">
            <div class="ticket-top">
                <p class="eyebrow">Nomor antrean Anda</p>
                <span class="pill pill-status" data-ticket-status>{{ $ticket->status === 'called' ? 'Giliran Anda' : $ticket->statusLabel() }}</span>
            </div>
            <div class="ticket-number">{{ $ticket->queue_number }}</div>
            <p class="ticket-meta">{{ $ticket->restaurant->name }}<br>{{ $ticket->party_size }} orang &middot; {{ $ticket->joined_at?->format('d M Y, H:i') }}</p>
            <div class="ticket-divider" aria-hidden="true"></div>
            <div class="stats">
                <div class="stat"><span>Antrean di depan</span><strong data-ticket-before>{{ max($position - 1, 0) }}</strong></div>
                <div class="stat"><span>Estimasi tunggu</span><strong>&plusmn;<span data-ticket-estimate>{{ $estimatedWait }}</span> <small>menit</small></strong></div>
            </div>
        </div>

        <p class="ticket-message" data-ticket-message data-messages='@json($messages)'>{{ $messages[$ticket->isActive() ? $ticket->status : 'ended'] }}</p>
        <p class="live-note center" data-live-note>Halaman ini diperbarui otomatis.</p>

        @if($ticket->isActive())
            <form class="center" method="post" action="{{ route('queue.cancel', $ticket->public_token) }}" data-ticket-cancel onsubmit="return confirm('Batalkan antrean ini?')">@csrf<button class="button button-ghost-danger" type="submit">Batalkan antrean</button></form>
        @endif
    </section>
    <script src="{{ asset('js/queue-poll.js') }}?v=ui-9" defer></script>
</x-layouts.customer>
