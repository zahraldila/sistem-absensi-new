<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>@yield('title', 'Auth | Sistem Absensi')</title>

    <!-- FontAwesome 6 CDN for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" integrity="sha512-z3gLpd7yknf1YoNbCzqRKc4qyor8gaKU1qmn+CShxbuBusANI9QpRohGBreCFkKxLhei6S9CQXFEbbKuqLg0DA==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    @vite([
        'resources/css/app.css',
        'resources/js/app.js'
    ])
    
    <style>
        :root {
            @php
                $primaryColor = \App\Models\Setting::get('primary_color', '#123D91');
            @endphp
            --color-primary: {{ $primaryColor }};
            --color-primary-hover: color-mix(in srgb, {{ $primaryColor }} 80%, black);
        }
    </style>
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 flex flex-col justify-center items-center p-4 sm:p-6 md:p-8">
    {{-- Offline connectivity warning banner --}}
    @include('components.layout.offline-banner')
    <div class="w-full max-w-[440px] my-auto">
        @yield('content')
    </div>
</body>
</html>
