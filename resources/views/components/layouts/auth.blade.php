<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Controle de Estoque' }}</title>
    <link rel="stylesheet" href="{{ asset('css/app-ui.css') }}?v={{ filemtime(public_path('css/app-ui.css')) }}">
</head>
<body class="auth-page">
    <main class="auth-card">
        {{ $slot }}
    </main>

    @include('components.sweetalert')
</body>
</html>
