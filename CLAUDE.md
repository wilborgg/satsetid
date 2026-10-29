# SatSet — Antrean Virtual F&B

Customer scan QR di outlet, mengambil nomor antrean, lalu memantau giliran dari HP (dan WhatsApp, bila aktif). Panduan menjalankan ada di `README.md`; panduan demo di `docs/PANDUAN-PRESENTASI.md`; checklist uji di `docs/PANDUAN-UJI-COBA.md`.

## Alur customer

Ada dua mode pendaftaran. Keduanya berawal dari halaman outlet `/r/{slug}` (isi QR).

1. **WhatsApp chatbot** (default, butuh `WHATSAPP_ENABLED=true`): tombol "Lanjut via WhatsApp" membuka `wa.me/<nomor>?text=ANTRE <slug>` → bot menanyakan jumlah tamu → `YA` untuk konfirmasi → tiket dibuat. State percakapan di `whatsapp_conversations` (`restaurant_selected` → `waiting_party_size` → `waiting_confirmation` → `queue_created`, kedaluwarsa 30 menit).
2. **Daftar lewat web** (`SATSET_WEB_SIGNUP=true`): form jumlah tamu di halaman outlet → `POST /r/{slug}/queue` → langsung ke halaman tiket. Satu tiket aktif per browser per outlet (disimpan di session), throttle 6/menit.

Halaman tiket `/queue/{public_token}` di-poll setiap 10 detik; halaman outlet setiap 15 detik.

## Stack dan file penting

- Laravel 13, PHP 8.3+, MySQL (dev), SQLite in-memory (test). Blade + CSS + JS biasa di `public/` tanpa build step.
- `app/Services/QueueService.php`: nomor antrean, posisi, estimasi, call/complete/skip/cancel. Semua perubahan di dalam transaksi dengan `lockForUpdate` pada restoran.
- `app/Services/WhatsAppChatbotService.php`: state machine chatbot.
- `app/Services/WhatsAppService.php`: outbox `whatsapp_logs` + pengiriman Meta/Twilio.
- `app/Services/QueueWhatsAppNotificationService.php`: notifikasi tinggal 3, tinggal 1, dipanggil, dibatalkan.
- `app/Http/Controllers/WhatsAppWebhookController.php` (Meta) dan `TwilioWebhookController.php` (Twilio).
- `routes/web.php`: route admin dibagi tiga grup: semua admin, per outlet (`can:manage-restaurant`), dan khusus pemilik (`can:owner`).
- `routes/console.php`: `satset:admin` (buat akun pemilik), `satset:lan` (server untuk diakses HP di Wi-Fi yang sama), `whatsapp:send-pending`.

## Peran admin

- `users.role`: `owner` (Pemilik SatSet) atau `outlet_admin` (kasir). Pivot `restaurant_user`: satu akun bisa memegang beberapa outlet, dan satu outlet bisa punya beberapa akun.
- Gate `owner` dan `manage-restaurant` ada di `AppServiceProvider`. Daftar admin selalu difilter dengan `User::accessibleRestaurants()`.
- Admin outlet boleh mengubah pengaturan outlet, **kecuali** nama, prefix, dan status aktif (dicek juga di server).
- `users.active=false` → tidak bisa login dan langsung di-logout (middleware `admin.active`). Pemilik tidak bisa menurunkan peran atau menonaktifkan akunnya sendiri.

## Keputusan desain (jangan dilanggar)

- Membuka halaman atau mengikuti link WhatsApp **tidak pernah** membuat tiket. Tiket hanya dibuat oleh konfirmasi chatbot atau submit form web.
- `party_size` adalah jumlah tamu dalam satu kelompok. Satu kelompok mendapat satu nomor antrean.
- Slug outlet tidak berubah walau nama berganti, supaya QR yang sudah dicetak tetap berlaku. Prefix tidak bisa diubah setelah ada riwayat antrean.
- Satu nomor WhatsApp hanya punya satu tiket aktif per outlet.
- Webhook memverifikasi signature dari raw request sebelum membaca isi (Meta: `X-Hub-Signature-256`; Twilio: `X-Twilio-Signature` dengan URL dari `APP_URL`).
- ID pesan masuk disimpan unik (`whatsapp_incoming_messages`), sehingga pesan yang dikirim ulang provider diproses sekali.
- Pesan keluar ditulis ke outbox dulu (dengan `event_key` unik), baru dikirim lewat job `DeliverWhatsAppMessage`. Notifikasi yang sama tidak pernah terkirim dua kali. Error yang dicatat sudah disanitasi; kredensial tidak pernah masuk log.
- Di luar jendela 24 jam sejak pesan terakhir customer, kirim template (`WHATSAPP_TEMPLATE_*`: nama template Meta atau Content SID Twilio); kalau tidak ada template, catat `template_required`.
- Link yang dikirim lewat WhatsApp dibangun dari `APP_URL`, bukan dari host request.
- QR memakai `APP_URL`, kecuali `APP_URL` masih localhost dan admin membuka lewat alamat jaringan (supaya bisa di-scan HP di Wi-Fi yang sama).
- Hanya proxy di loopback (`127.0.0.1`, `::1`) yang dipercaya, untuk tunnel `cloudflared`.
- Endpoint polling memanggil `session()->reflash()` supaya tidak menghabiskan flash message.

## Konfigurasi `.env`

```
APP_URL=                     # alamat publik (tunnel/domain); dipakai untuk QR dan link WhatsApp
SATSET_WEB_SIGNUP=false      # true = daftar lewat form web
WHATSAPP_ENABLED=false
WHATSAPP_PROVIDER=meta       # meta | twilio
WHATSAPP_PHONE_NUMBER=       # nomor WhatsApp publik, format 62.../1... (Twilio: juga nomor pengirim)
TWILIO_ACCOUNT_SID=
TWILIO_AUTH_TOKEN=
WHATSAPP_ACCESS_TOKEN=       # Meta
WHATSAPP_PHONE_NUMBER_ID=    # Meta
WHATSAPP_BUSINESS_ACCOUNT_ID=
WHATSAPP_APP_SECRET=
WHATSAPP_VERIFY_TOKEN=
WHATSAPP_API_VERSION=        # format vXX.X
WHATSAPP_TEMPLATE_THREE_AHEAD= / _ONE_AHEAD= / _CALLED= / _CANCELLED=
```

Jangan pernah commit `.env` atau nilai asli kredensial. Saat aplikasi bisa diakses publik, `APP_DEBUG` harus `false`.

## Konvensi

- Semua teks UI dalam Bahasa Indonesia. Label status tiket diambil dari `QueueTicket::STATUS_LABELS`.
- Warna dan ukuran didefinisikan sebagai token di `:root` pada `public/css/satset.css` (customer) dan `public/css/admin.css` (admin). Warna brand `#2e6d6d`.
- Setelah mengubah CSS/JS, naikkan query versi (`?v=ui-N`) di layout supaya cache browser diperbarui.
- Test: `php artisan test`. HTTP ke Meta/Twilio selalu di-fake. Pengaturan WhatsApp untuk test dikunci di `phpunit.xml`, jadi tidak terpengaruh `.env` lokal.
- Nilai contoh yang formatnya mirip kredensial asli (misalnya Account SID Twilio) dipecah, contohnya `'AC'.'000…'`, supaya tidak memicu secret scanning GitHub.

## Belum dikerjakan (backlog)

Diurutkan kira-kira dari yang paling penting.

1. **Notifikasi bisa terlewat saat posisi melompat.** `notifyWaitingPositions` masih memakai `remaining === 3` / `=== 1`. Kalau dua orang batal sekaligus, notifikasi "tinggal 3" tidak pernah terkirim. Ganti ke `<=` dengan penanda `*_notified_at`.
2. **Threshold notifikasi di-hardcode** (3 dan 1). Jadikan kolom per restoran, misalnya `notify_threshold`.
3. **Nomor antrean tidak pernah di-reset.** Sequence dihitung per restoran sepanjang masa; seharusnya per `(restaurant_id, service_date)`.
4. **Tombol reset antrean harian** di halaman admin.
5. **Perintah chatbot setelah tiket dibuat**: `STATUS`/`CEK`, dan `BATAL` dengan konfirmasi. Saat ini pesan apa pun setelah tiket dibuat hanya dibalas ringkasan tiket.
6. **Tiket web → WhatsApp**: menghubungkan tiket dari form web ke WhatsApp lewat kode acak sekali pakai (alfabet tanpa 0/O/1/I), supaya customer web juga bisa menerima notifikasi.
7. **Privasi (UU PDP)**: anonimkan nomor HP dan isi pesan N hari setelah tiket selesai (scheduled command), dan tampilkan pemberitahuan singkat di halaman pendaftaran.
8. **Efisiensi notifikasi**: posisi tiap tiket dihitung dengan satu query per tiket (N+1).
9. **Laporan per outlet** untuk pemilik: tamu per hari, rata-rata tunggu, tingkat dilewati, jam tersibuk.
10. **Hosting permanen** (rencana: versi Firebase). Saat ini demo berjalan dari laptop lewat Cloudflare Tunnel.
