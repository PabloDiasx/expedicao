<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Controle de Estoque</title>
    <link rel="stylesheet" href="{{ asset('css/app-ui.css') }}?v={{ filemtime(public_path('css/app-ui.css')) }}">
</head>
<body class="auth-page">
    <main class="auth-card">
        <h1 class="title title-welcome">Controle de Estoque</h1>
        <div class="actions-center">
            <a class="btn" href="{{ route('login') }}">Acessar</a>
        </div>
    </main>
</body>
</html>
