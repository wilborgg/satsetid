# SatSet — Antrean Digital Restoran dengan Notifikasi WhatsApp

Project ini dibangun dari nol. Ini pengganti versi lama (github.com/WhyNic/satset) yang memakai Web Push. Versi lama boleh dibaca sebagai referensi, tapi jangan disalin mentah-mentah. Bug-bug yang tercatat di bagian "Pelajaran dari versi lama" jangan diulang.

## Alur utama

1. Customer datang ke restoran, lalu scan QR code yang ditempel di restoran.
2. QR membuka halaman web mobile milik restoran itu. Halaman menampilkan nama restoran, jumlah antrean aktif, estimasi waktu tunggu, dan status kepadatan.
3. Customer menekan "Ambil Antrean". Sistem membuat tiket, lalu menampilkan halaman tiket berisi nomor antrean, posisi, estimasi, dan tombol "Hubungkan ke WhatsApp".
4. Tombol itu membuka `https://wa.me/<NOMOR_BOT>?text=ANTRI%20<KODE>`. Customer menekan kirim di WhatsApp.
5. Bot menerima pesan lewat webhook, menghubungkan nomor WA ke tiket, lalu membalas konfirmasi beserta nomor antrean dan posisi.
6. Bot mengirim notifikasi saat sisa antrean di depan customer <= threshold (default 5), dan saat customer dipanggil.
7. Customer bisa bertanya status ke bot kapan saja.

## Stack (default, ubah kalau perlu)

- Laravel 13, PHP 8.3+, MySQL (development dan production), SQLite in-memory untuk test
- Blade + CSS + vanilla JS, mobile-first
- WhatsApp Cloud API milik Meta, langsung tanpa BSP, lewat Graph API
- Queue: driver `database`, dan semua pengiriman WA lewat job

## Keputusan desain (penting)

### WhatsApp: customer service window
- Bisnis hanya boleh mengirim pesan bebas (non-template) dalam 24 jam sejak pesan terakhir dari customer. Karena itu customer HARUS mengirim pesan duluan (langkah 4).
- Sejak 1 Okt 2026: 1.000 service message gratis per nomor per bulan, sesudahnya berbayar. Template utility selalu berbayar.
- Jadi hemat pesan. Kirim hanya di momen penting: konfirmasi terhubung, sisa <= threshold, dipanggil, dibatalkan/dilewati, dan balasan saat customer bertanya. Jangan kirim update setiap kali posisi berubah.
- Sebelum mengirim, cek apakah window masih terbuka (pesan masuk terakhir < 24 jam). Kalau sudah tertutup, jangan kirim pesan bebas, cukup catat di log. Template message tidak dipakai di versi awal.

### Keamanan penghubungan tiket ke WA
- `wa_link_code`: string acak 6–8 karakter dari alfabet tanpa karakter ambigu (tanpa 0/O/1/I). Kode ini unik dan TERPISAH dari nomor antrean yang ditampilkan (A-012). Nomor antrean bisa ditebak, kode ini tidak boleh.
- Kode hanya bisa dipakai sekali. Kalau tiket sudah terhubung ke `wa_id` tertentu, pesan `ANTRI <kode>` dari nomor lain ditolak.
- Webhook POST WAJIB memverifikasi `X-Hub-Signature-256`: HMAC-SHA256 dari raw body memakai `WHATSAPP_APP_SECRET`, dibandingkan dengan `hash_equals`. Kalau signature tidak valid, balas 401 dan jangan proses.
- Endpoint webhook dikecualikan dari CSRF.
- Idempotensi: simpan ID pesan WA (wamid) yang masuk. Pesan yang sudah pernah diproses diabaikan, karena Meta bisa mengirim ulang.
- Rate limit pengambilan tiket. Satu tiket aktif per perangkat per restoran, lewat cookie yang menyimpan token tiket.

### Notifikasi antrean
- Posisi = jumlah tiket aktif (`waiting` + `called`) dengan `sequence_number` <= milik tiket ini. Sisa di depan = posisi - 1.
- Evaluasi notifikasi dijalankan setelah SETIAP perubahan status antrean: call next, complete, skip, cancel (customer maupun admin), dan reset. Bungkus dalam satu method, misalnya `QueueNotifier::evaluate(Restaurant)`, lalu dispatch sebagai job.
- Kondisi: `remaining <= restaurant.notify_threshold` DAN `almost_turn_notified_at` masih null. Pakai `<=`, BUKAN `===`, karena posisi bisa melompat saat ada yang batal.
- Notifikasi "dipanggil" dikirim sekali, ditandai dengan `called_notified_at`.
- Threshold disimpan per restoran (kolom `notify_threshold`, default 5).

### Bot (tanpa AI, berbasis keyword/tombol)
- `ANTRI <kode>` → hubungkan tiket, balas nomor, posisi, dan estimasi
- `STATUS` / `CEK` → posisi dan estimasi tiket aktif milik `wa_id` ini
- `BATAL` → minta konfirmasi dulu (reply button Ya/Tidak), baru batalkan
- Pesan lain → menu bantuan dengan reply buttons
- Matching keyword tidak case-sensitive dan mengabaikan spasi berlebih.

## Model data (awal)

- `restaurants`: name, slug, prefix, category, address, average_service_minutes, max_wait_minutes, notify_threshold (default 5), active
- `queue_tickets`: restaurant_id, public_token (uuid), wa_link_code (unique), queue_number, sequence_number, service_date, status (waiting/called/completed/skipped/cancelled), wa_id (nullable), wa_linked_at, last_inbound_at, joined_at, called_at, completed_at, cancelled_at, almost_turn_notified_at, called_notified_at
- `whatsapp_messages`: direction (in/out), wa_message_id (unique, nullable untuk outbound yang gagal), wa_id, queue_ticket_id (nullable), type, body, status, error, timestamps
- `users` (admin) + pivot `restaurant_user`: admin hanya bisa mengelola restoran yang ditugaskan kepadanya (Policy). Peran `super_admin` boleh mengelola semua restoran.

Nomor antrean di-reset per hari: sequence dihitung per `(restaurant_id, service_date)`, dibuat di dalam transaksi dengan lock.

## Fitur admin

- Login dengan throttle
- CRUD restoran (super admin)
- Halaman operasional antrean: Call Next, Complete, Skip, Cancel tiket, Reset. Tampilkan juga apakah tiket sudah terhubung ke WA.
- Generate dan download QR code per restoran (PNG/SVG) yang mengarah ke URL halaman restoran

## Konfigurasi `.env`

```
WHATSAPP_PHONE_NUMBER_ID=
WHATSAPP_BUSINESS_NUMBER=      # nomor untuk link wa.me, format 62xxxx
WHATSAPP_ACCESS_TOKEN=         # permanent token dari System User
WHATSAPP_APP_SECRET=           # untuk verifikasi signature
WHATSAPP_VERIFY_TOKEN=         # bebas, untuk handshake webhook
WHATSAPP_GRAPH_VERSION=        # cek versi terbaru di dokumentasi Meta
```

Jangan pernah commit nilai asli. Saat development pakai nomor test dari Meta (penerima harus didaftarkan manual) dan tunnel HTTPS (ngrok/Cloudflare Tunnel), karena Meta tidak bisa memanggil localhost.

## Testing

- Feature test untuk: handshake webhook, penolakan signature tidak valid, `ANTRI <kode>` sukses, kode dipakai ulang dari nomor lain ditolak, pesan duplikat diabaikan, STATUS, BATAL, notifikasi threshold terkirim tepat sekali walau posisi melompat, notifikasi tidak terkirim kalau window sudah tertutup.
- HTTP ke Graph API selalu di-fake (`Http::fake`) di test. Test tidak boleh memanggil Meta sungguhan.

## Privasi

Nomor HP (`wa_id`) adalah data pribadi (UU PDP). Hapus atau anonimkan `wa_id` dan isi pesan setelah N hari sejak tiket selesai (scheduled command). Cantumkan pemberitahuan singkat di halaman ambil antrean.

## Pelajaran dari versi lama (jangan diulang)

- Notifikasi hanya dicek saat Call Next dan memakai `=== 3`, sehingga notifikasi terlewat saat posisi melompat.
- Threshold di-hardcode di beberapa tempat (backend dan JS).
- Notifikasi dikirim sinkron di dalam request admin, dengan N+1 query posisi.
- Tidak ada rate limit pengambilan tiket, tidak ada otorisasi per restoran, tidak ada throttle login.
- Nomor antrean tidak pernah di-reset.
- Tidak ada fitur QR, padahal alur dimulai dari scan QR.

## Urutan pengerjaan yang disarankan

1. Scaffold Laravel, migrasi, model, seeder
2. Halaman customer: restoran → ambil tiket → halaman tiket dengan link WA
3. QueueService + QueueNotifier, beserta test
4. Webhook WA (verify, signature, idempotensi) + parser perintah bot + WhatsAppService (kirim pesan), beserta test
5. Panel admin + policy + QR generator
6. Uji end-to-end dengan nomor test Meta lewat tunnel
