<x-layouts.auth :title="'Nova Senha'" :tenant="$tenant">
    <div class="logo-wrap">
        <img src="{{ asset('img/agilizaSemFUNDOmelhorada.png') }}" alt="Logo Agiliza">
    </div>

    <h1 class="title title-welcome">NOVA SENHA</h1>

    <form method="POST" action="{{ route('password.update') }}" class="stack-24" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div>
            <label class="field-label" for="email">E-mail:</label>
            <input
                class="input"
                id="email"
                name="email"
                type="email"
                value="{{ old('email', $email) }}"
                autocomplete="email"
                required
            >
        </div>

        <div>
            <label class="field-label" for="password">Nova senha:</label>
            <input
                class="input"
                id="password"
                name="password"
                type="password"
                autocomplete="new-password"
                required
            >
        </div>

        <div>
            <label class="field-label" for="password_confirmation">Confirmar senha:</label>
            <input
                class="input"
                id="password_confirmation"
                name="password_confirmation"
                type="password"
                autocomplete="new-password"
                required
            >
        </div>

        <div class="actions-center">
            <button type="submit" class="btn">Salvar nova senha</button>
        </div>
    </form>

    <div class="auth-footer-link">
        <a class="link" href="{{ route('login') }}">Voltar para login</a>
    </div>
</x-layouts.auth>
