<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Delivery')</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: { extend: { colors: { brand: { 600: '#1e5f8a', 700: '#164a6d' } } } },
        };
    </script>
</head>
<body class="bg-slate-100 text-slate-900 font-sans min-h-screen">
@auth
<header class="bg-brand-600 text-white px-5 py-3 flex justify-between items-center flex-wrap gap-2">
    <nav class="flex flex-wrap gap-4 text-sm font-semibold">
        <a class="hover:underline" href="{{ route('dashboard') }}">Dashboard</a>
        <a class="hover:underline" href="{{ route('import.index') }}">Importar eventos</a>
        <a class="hover:underline" href="{{ route('repartidores.index') }}">Repartidores</a>
        <a class="hover:underline" href="{{ route('repartidores.mapa') }}">Mapa en vivo</a>
        <a class="hover:underline" href="{{ route('retiro.index') }}">Retiro en sitio</a>
        <a class="hover:underline" href="{{ route('pos.index') }}">POS</a>
    </nav>
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="text-sm bg-brand-700 hover:bg-brand-700/80 px-3 py-1.5 rounded-md">
            Salir ({{ auth()->user()->email }})
        </button>
    </form>
</header>
@endauth
<main class="max-w-5xl mx-auto p-5">
    @if (session('status'))
        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-2 rounded-md mb-5 text-sm">
            {{ session('status') }}
        </div>
    @endif
    @if ($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-2 rounded-md mb-5 text-sm">
            {{ $errors->first() }}
        </div>
    @endif
    @yield('content')
</main>
</body>
</html>
