@props(['title' => 'SatSet'])
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#2e6d6d">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }}</title>
    <link rel="icon" href="{{ asset('images/logo-128.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('icons/icon-192.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/satset.css') }}?v=ui-9">
    <script src="{{ asset('js/customer.js') }}?v=ui-9" defer></script>
</head>
<body>
    <a class="skip-link" href="#main">Lewati navigasi</a>
    <header class="topbar">
        <div class="topbar-inner">
            <a class="brand" href="{{ route('home') }}" aria-label="SatSet beranda"><img class="brand-mark" src="{{ asset('images/logo-128.png') }}" alt="" width="32" height="32">SatSet</a>
            <span class="topbar-caption">Antre tanpa berdiri</span>
        </div>
    </header>
    <main id="main">
        @if(session('status'))<div class="flash" role="status">{{ session('status') }}</div>@endif
        {{ $slot }}
    </main>
    <footer class="footer">
        <span><strong>SatSet</strong> &middot; Virtual queue untuk F&amp;B</span>
        <span>Waktu Anda lebih berharga.</span>
    </footer>
</body>
</html>
