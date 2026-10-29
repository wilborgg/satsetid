<x-layouts.customer title="SatSet | Virtual Queue for F&B">
    <section class="home-hero">
        <div class="home-hero-inner">
            <p class="eyebrow eyebrow-light">Virtual queue untuk F&amp;B</p>
            <h1>Antre tanpa harus berdiri menunggu.</h1>
            <p class="home-hero-text">Scan QR SatSet di outlet untuk langsung masuk antrean. {{ config('satset.web_signup') ? 'Pantau giliran Anda dari HP.' : 'Kabar giliran dikirim lewat WhatsApp.' }}</p>
            <ul class="hero-points">
                <li>Tanpa akun</li>
                <li>Tanpa install aplikasi</li>
            </ul>
        </div>
    </section>

    <section class="page page-wide">
        <p class="eyebrow">Cara antre</p>
        <h2 class="section-title">Tiga langkah, tanpa berdiri.</h2>
        <ol class="steps steps-row">
            <li><span class="step-number">1</span><div><strong>Scan QR di outlet</strong><span>Cari QR SatSet di kasir atau pintu masuk.</span></div></li>
            @if(config('satset.web_signup'))
                <li><span class="step-number">2</span><div><strong>Ambil nomor antrean</strong><span>Pilih jumlah tamu, nomor antrean langsung muncul di HP.</span></div></li>
                <li><span class="step-number">3</span><div><strong>Nikmati waktu Anda</strong><span>Pantau posisi antrean dari halaman tiket, lalu kembali saat giliran tiba.</span></div></li>
            @else
                <li><span class="step-number">2</span><div><strong>Lanjutkan di WhatsApp</strong><span>Pilih jumlah tamu dan konfirmasi antrean Anda.</span></div></li>
                <li><span class="step-number">3</span><div><strong>Nikmati waktu Anda</strong><span>Kami kabari saat giliran sudah dekat.</span></div></li>
            @endif
        </ol>
    </section>
</x-layouts.customer>
