<x-layouts.auth :title="'Esqueci a Senha'" :tenant="$tenant">
    <div class="logo-wrap">
        <img src="{{ asset('img/agilizaSemFUNDOmelhorada.png') }}" alt="Logo Agiliza">
    </div>

    <h1 class="title title-welcome">REDEFINIR SENHA</h1>

    <p class="muted auth-note">
        Informe o e-mail do usuario para gerar o link de redefinicao.
    </p>

    <form method="POST" action="{{ route('password.email') }}" class="stack-24" novalidate>
        @csrf
        <div>
            <label class="field-label" for="email">E-mail:</label>
            <input
                class="input"
                id="email"
                name="email"
                type="email"
                value="{{ old('email') }}"
                autocomplete="email"
                required
            >
        </div>

        <div class="actions-center">
            <button type="submit" class="btn">Gerar link</button>
        </div>
    </form>

    <div class="auth-footer-link">
        <a class="link" href="{{ route('login') }}">Voltar para login</a>
    </div>

    @if (session('password_reset_link'))
        @push('sweetalert')
            <script>
                (function () {
                    const resetLink = @json(session('password_reset_link'));
                    window.appAlert({
                        icon: 'success',
                        title: 'Link de redefinicao',
                        html: 'Abra o link para redefinir a senha:',
                        footer: '<a href="' + resetLink + '" target="_self">' + resetLink + '</a>',
                        confirmButtonText: 'Fechar',
                    });
                })();
            </script>
        @endpush
    @endif
</x-layouts.auth>
