<x-layouts.auth :title="'Login - Controle de Estoque'" :tenant="$tenant">
    <div class="logo-wrap">
        <img src="{{ asset('img/agilizaSemFUNDOmelhorada.png') }}" alt="Logo Agiliza">
    </div>

    <h1 class="title title-welcome">SEJA BEM VINDO</h1>

    <form method="POST" action="{{ route('login.store') }}" class="stack-24" novalidate>
        @csrf
        <div>
            <label class="field-label field-label-compact" for="login">Usuario/E-mail:</label>
            <input
                class="input"
                id="login"
                name="login"
                type="text"
                value="{{ old('login') }}"
                autocomplete="username"
                required
            >
        </div>

        <div class="stack-8">
            <div class="row-between">
                <label class="field-label field-label-compact field-label-inline" for="password">Senha:</label>
                <a class="link" href="{{ route('password.request') }}">esqueceu a senha</a>
            </div>
            <div class="password-field">
                <input
                    class="input input-password"
                    id="password"
                    name="password"
                    type="password"
                    autocomplete="current-password"
                    required
                >
                <button
                    type="button"
                    class="password-toggle"
                    data-toggle-password="password"
                    aria-label="Mostrar senha"
                    aria-pressed="false"
                >
                    <svg data-eye-open viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M1 12C1 12 5 5 12 5C19 5 23 12 23 12C23 12 19 19 12 19C5 19 1 12 1 12Z" stroke="currentColor" stroke-width="2"/>
                        <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/>
                    </svg>
                    <svg data-eye-off viewBox="0 0 24 24" fill="none" aria-hidden="true" class="is-hidden">
                        <path d="M3 3L21 21" stroke="currentColor" stroke-width="2"/>
                        <path d="M10.6 10.6C10.2 11 10 11.5 10 12C10 13.1 10.9 14 12 14C12.5 14 13 13.8 13.4 13.4" stroke="currentColor" stroke-width="2"/>
                        <path d="M17.9 17.9C16.2 18.7 14.2 19.2 12 19.2C5 19.2 1 12 1 12C1.9 10.4 3.1 9 4.6 7.9" stroke="currentColor" stroke-width="2"/>
                        <path d="M9.2 5.3C10.1 5.1 11 5 12 5C19 5 23 12 23 12C22.5 12.9 21.9 13.8 21.2 14.6" stroke="currentColor" stroke-width="2"/>
                    </svg>
                </button>
            </div>
            <label class="remember">
                <input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}>
                Manter conectado
            </label>
        </div>

        <div class="actions-center">
            <button type="submit" class="btn">Logar</button>
        </div>
    </form>

    <script>
        (function () {
            const toggle = document.querySelector('[data-toggle-password="password"]');
            const passwordInput = document.getElementById('password');

            if (!toggle || !passwordInput) {
                return;
            }

            const eyeOpen = toggle.querySelector('[data-eye-open]');
            const eyeOff = toggle.querySelector('[data-eye-off]');

            toggle.addEventListener('click', function () {
                const isHidden = passwordInput.type === 'password';
                passwordInput.type = isHidden ? 'text' : 'password';
                toggle.setAttribute('aria-pressed', isHidden ? 'true' : 'false');
                toggle.setAttribute('aria-label', isHidden ? 'Ocultar senha' : 'Mostrar senha');

                if (eyeOpen && eyeOff) {
                    eyeOpen.classList.toggle('is-hidden', isHidden);
                    eyeOff.classList.toggle('is-hidden', !isHidden);
                }
            });
        })();
    </script>
</x-layouts.auth>
