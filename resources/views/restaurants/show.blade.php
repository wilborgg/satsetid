<x-layouts.customer :title="$restaurant->name.' | SatSet'">
    <article class="page" data-outlet-status="{{ route('outlets.status', $restaurant->slug) }}">
        <x-customer.restaurant-image class="outlet-photo" :restaurant="$restaurant" variant="detail" loading="eager" />

        <header class="outlet-header">
            <p class="eyebrow">{{ $restaurant->category }}</p>
            <h1>{{ $restaurant->name }}</h1>
            @if($restaurant->description)<p class="muted">{{ $restaurant->description }}</p>@endif
            <span class="pill {{ $snapshot['available'] ? 'pill-open' : 'pill-closed' }}" data-outlet-availability>{{ $snapshot['available'] ? 'Antrean sedang dibuka' : 'Antrean sedang ditutup' }}</span>
        </header>

        <section class="card" aria-label="Kondisi antrean">
            <div class="serving">
                <span>Sedang dilayani</span>
                <strong data-outlet-current>{{ $snapshot['current_number'] ?? 'Belum ada' }}</strong>
            </div>
            <div class="stats">
                <div class="stat"><span>Sedang menunggu</span><strong><span data-outlet-waiting>{{ $snapshot['waiting_count'] }}</span> <small>kelompok</small></strong></div>
                <div class="stat"><span>Estimasi tunggu</span><strong>&plusmn;<span data-outlet-estimate>{{ $snapshot['estimated_wait'] }}</span> <small>menit</small></strong></div>
            </div>
            <p class="live-note" data-live-note>Estimasi dapat berubah sesuai pelayanan outlet.</p>
        </section>

        <section class="cta">
            @if($webSignup && $myTicket)
                <div class="card my-ticket">
                    <span>Anda sudah memiliki antrean di outlet ini</span>
                    <strong>{{ $myTicket->queue_number }}</strong>
                    <a class="button button-primary button-block" href="{{ route('queue.show', $myTicket->public_token) }}">Lihat tiket saya</a>
                </div>
            @elseif($webSignup)
                <form class="card signup" method="post" action="{{ route('outlets.join', $restaurant->slug) }}" @if(!$snapshot['available']) hidden @endif data-outlet-cta>
                    @csrf
                    <label for="party-size"><strong>Berapa orang yang akan datang?</strong></label>
                    @if($restaurant->max_party_size <= 12)
                        <div class="party-picker" role="radiogroup" aria-label="Jumlah tamu">
                            @for($i = 1; $i <= $restaurant->max_party_size; $i++)
                                <label class="party-option">
                                    <input type="radio" name="party_size" value="{{ $i }}" @checked((int) old('party_size', min(2, $restaurant->max_party_size)) === $i) @if($i === 1) id="party-size" @endif>
                                    <span>{{ $i }}</span>
                                </label>
                            @endfor
                        </div>
                    @else
                        <input class="party-input" id="party-size" type="number" name="party_size" min="1" max="{{ $restaurant->max_party_size }}" value="{{ old('party_size', 2) }}" inputmode="numeric" required>
                    @endif
                    @error('party_size')<p class="form-error">{{ $message }}</p>@enderror
                    @error('restaurant')<p class="form-error">{{ $message }}</p>@enderror
                    <button class="button button-primary button-block" type="submit">Ambil nomor antrean</button>
                    <p class="cta-note">Tanpa login dan tanpa install aplikasi. Satu kelompok, satu nomor antrean.</p>
                </form>
                <p class="alert" data-outlet-closed @if($snapshot['available']) hidden @endif>Antrean sedang ditutup. Silakan hubungi staf outlet.</p>
            @elseif($whatsappUrl)
                <a class="button button-primary button-block" href="{{ route('outlets.whatsapp', $restaurant->slug) }}" @if(!$snapshot['available']) hidden @endif data-outlet-cta>Lanjut via WhatsApp</a>
                <p class="alert" data-outlet-closed @if($snapshot['available']) hidden @endif>Antrean sedang ditutup. Silakan hubungi staf outlet.</p>
                <p class="cta-note">Tanpa login. Nomor antrean dibuat setelah Anda konfirmasi di WhatsApp.</p>
            @else
                <p class="alert">Pendaftaran lewat WhatsApp belum tersedia. Silakan hubungi staf outlet.</p>
            @endif
        </section>

        <section class="card">
            <h2 class="card-title">Setelah ini</h2>
            <ol class="steps">
                @if($webSignup)
                    <li><span class="step-number">1</span><div><strong>Ambil nomor antrean</strong><span>Pilih jumlah tamu, lalu simpan halaman tiket Anda.</span></div></li>
                    <li><span class="step-number">2</span><div><strong>Nikmati waktu Anda</strong><span>Halaman tiket diperbarui otomatis. Kembali saat giliran Anda tiba.</span></div></li>
                @else
                    <li><span class="step-number">1</span><div><strong>Lanjutkan di WhatsApp</strong><span>Pilih jumlah tamu dan konfirmasi antrean Anda.</span></div></li>
                    <li><span class="step-number">2</span><div><strong>Nikmati waktu Anda</strong><span>Kami kabari saat giliran sudah dekat.</span></div></li>
                @endif
            </ol>
            <p class="fine-print">Maksimal {{ $restaurant->max_party_size }} orang per kelompok. Satu kelompok, satu nomor antrean.</p>
        </section>
    </article>
</x-layouts.customer>
