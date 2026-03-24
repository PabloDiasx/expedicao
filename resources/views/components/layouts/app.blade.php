@props([
    'title' => 'Painel',
    'pageClass' => '',
])
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} - Controle de Estoque</title>
    <link rel="stylesheet" href="{{ asset('css/app-ui.css') }}?v={{ filemtime(public_path('css/app-ui.css')) }}">
</head>
<body class="{{ trim('dashboard-page ' . $pageClass) }}">
    <div class="layout">
        <aside class="sidebar" id="dashboardSidebar">
            <div class="sidebar-header">
                <button
                    type="button"
                    class="sidebar-toggle"
                    id="sidebarToggle"
                    aria-label="Recolher menu lateral"
                    aria-expanded="true"
                >
                    <svg aria-hidden="true" viewBox="0 0 24 24" width="18" height="18" fill="none">
                        <path d="M15 6L9 12L15 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                </button>
                <h2 class="brand">Agiliza Sistemas</h2>
            </div>

            <form method="GET" action="{{ route('equipments.index') }}" class="search-box">
                <svg aria-hidden="true" viewBox="0 0 24 24" width="16" height="16" fill="none">
                    <circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"></circle>
                    <path d="M20 20L16.6 16.6" stroke="currentColor" stroke-width="2"></path>
                </svg>
                <input type="text" name="q" placeholder="Search" aria-label="Search" value="{{ request('q') }}">
            </form>

            <nav class="sidebar-nav">
                @php
                    $etapaAtual = (string) request('etapa', '');
                    $isProducao = request()->routeIs('production.*');
                    $isExpedicao = request()->routeIs('expedition.*');
                @endphp

                <a href="{{ route('dashboard') }}" class="nav-item {{ request()->routeIs('dashboard') ? 'is-active' : '' }}">
                    <svg aria-hidden="true" viewBox="0 0 24 24" width="16" height="16" fill="none">
                        <path d="M4 20H20" stroke="currentColor" stroke-width="2" stroke-linecap="round"></path>
                        <rect x="6" y="11" width="3" height="7" rx="1" stroke="currentColor" stroke-width="2"></rect>
                        <rect x="11" y="8" width="3" height="10" rx="1" stroke="currentColor" stroke-width="2"></rect>
                        <rect x="16" y="5" width="3" height="13" rx="1" stroke="currentColor" stroke-width="2"></rect>
                    </svg>
                    <span>Dashboard</span>
                </a>

                <a href="{{ route('production.index', ['etapa' => 'solda']) }}" class="nav-item {{ $isProducao && $etapaAtual === 'solda' ? 'is-active' : '' }}">
                    <svg aria-hidden="true" viewBox="0 0 24 24" width="16" height="16" fill="none">
                        <path d="M4 20H20" stroke="currentColor" stroke-width="2" stroke-linecap="round"></path>
                        <path d="M7 16L11 12L14 14L18 9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                    <span>Solda</span>
                </a>

                <a href="{{ route('production.index', ['etapa' => 'pintura']) }}" class="nav-item {{ $isProducao && $etapaAtual === 'pintura' ? 'is-active' : '' }}">
                    <svg aria-hidden="true" viewBox="0 0 24 24" width="16" height="16" fill="none">
                        <path d="M6 5H18L16 11H8L6 5Z" stroke="currentColor" stroke-width="2"></path>
                        <path d="M8 11V17C8 18.1 8.9 19 10 19H14C15.1 19 16 18.1 16 17V11" stroke="currentColor" stroke-width="2"></path>
                    </svg>
                    <span>Pintura</span>
                </a>

                <a href="{{ route('production.index', ['etapa' => 'montagem']) }}" class="nav-item {{ $isProducao && ($etapaAtual === '' || $etapaAtual === 'montagem') ? 'is-active' : '' }}">
                    <svg aria-hidden="true" viewBox="0 0 24 24" width="16" height="16" fill="none">
                        <rect x="3" y="4" width="18" height="14" rx="2" stroke="currentColor" stroke-width="2"></rect>
                        <path d="M7 8H10M14 8H17M7 12H10M14 12H17M12 18V21" stroke="currentColor" stroke-width="2" stroke-linecap="round"></path>
                    </svg>
                    <span>Montagem</span>
                </a>

                <a href="{{ route('expedition.index') }}" class="nav-item {{ $isExpedicao && $etapaAtual !== 'carregamento' ? 'is-active' : '' }}">
                    <svg aria-hidden="true" viewBox="0 0 24 24" width="16" height="16" fill="none">
                        <path d="M3 7H15V17H3V7Z" stroke="currentColor" stroke-width="2"></path>
                        <path d="M15 10H19L21 13V17H15V10Z" stroke="currentColor" stroke-width="2"></path>
                        <circle cx="7.5" cy="17.5" r="1.5" stroke="currentColor" stroke-width="2"></circle>
                        <circle cx="17.5" cy="17.5" r="1.5" stroke="currentColor" stroke-width="2"></circle>
                    </svg>
                    <span>Expedi&ccedil;&atilde;o</span>
                </a>

                <a href="{{ route('expedition.index', ['etapa' => 'carregamento']) }}" class="nav-item {{ $isExpedicao && $etapaAtual === 'carregamento' ? 'is-active' : '' }}">
                    <svg aria-hidden="true" viewBox="0 0 24 24" width="16" height="16" fill="none">
                        <path d="M3 7H15V17H3V7Z" stroke="currentColor" stroke-width="2"></path>
                        <path d="M15 10H19L21 13V17H15V10Z" stroke="currentColor" stroke-width="2"></path>
                        <path d="M11 12L13 14L17 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                    <span>Carregamento</span>
                </a>

                <a href="{{ route('invoices.index') }}" class="nav-item {{ request()->routeIs('invoices.*') ? 'is-active' : '' }}">
                    <svg aria-hidden="true" viewBox="0 0 24 24" width="16" height="16" fill="none">
                        <path d="M6 3H14L18 7V21H6V3Z" stroke="currentColor" stroke-width="2"></path>
                        <path d="M14 3V7H18" stroke="currentColor" stroke-width="2"></path>
                        <path d="M9 11H15M9 15H15" stroke="currentColor" stroke-width="2" stroke-linecap="round"></path>
                    </svg>
                    <span>Notas Fiscais</span>
                </a>

                <a href="{{ route('equipments.index') }}" class="nav-item {{ request()->routeIs('equipments.*') ? 'is-active' : '' }}">
                    <svg aria-hidden="true" viewBox="0 0 24 24" width="16" height="16" fill="none">
                        <path d="M4 8H20V20H4V8Z" stroke="currentColor" stroke-width="2"></path>
                        <path d="M9 8V4H15V8" stroke="currentColor" stroke-width="2"></path>
                        <path d="M4 13H20" stroke="currentColor" stroke-width="2"></path>
                    </svg>
                    <span>Equipamentos</span>
                </a>

                <a href="{{ route('equipment-models.index') }}" class="nav-item {{ request()->routeIs('equipment-models.*') ? 'is-active' : '' }}">
                    <svg aria-hidden="true" viewBox="0 0 24 24" width="16" height="16" fill="none">
                        <rect x="3" y="5" width="18" height="14" rx="2" stroke="currentColor" stroke-width="2"></rect>
                        <path d="M7 10H17M7 14H13" stroke="currentColor" stroke-width="2" stroke-linecap="round"></path>
                    </svg>
                    <span>Modelos</span>
                </a>
            </nav>

            <div class="sidebar-footer">
                <div class="account-menu" id="accountMenu">
                    <button
                        type="button"
                        class="account-trigger"
                        id="accountMenuTrigger"
                        aria-expanded="false"
                        aria-haspopup="true"
                        aria-controls="accountMenuPanel"
                    >
                        <div class="sidebar-user">
                            <div class="avatar">{{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}</div>
                            <div class="user-meta">
                                <div>{{ auth()->user()->name ?? 'Usuario' }}</div>
                                <div>{{ auth()->user()->email ?? '-' }}</div>
                            </div>
                        </div>
                    </button>

                    <div class="account-menu-panel" id="accountMenuPanel" role="menu" aria-hidden="true">
                        <button type="button" id="accountSettingsBtn" class="account-action" role="menuitem">
                            Configurações
                        </button>

                        <button type="button" id="themeToggleBtn" class="account-action account-action-toggle" role="menuitem" aria-pressed="false">
                            <span id="themeToggleLabel">Modo escuro</span>
                            <span class="theme-switch" aria-hidden="true"></span>
                        </button>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="account-action account-action-danger" role="menuitem">Sair</button>
                        </form>
                    </div>
                </div>
            </div>
        </aside>

        <main class="content">
            <div class="topbar">
                <h1>{{ $title }}</h1>
            </div>

            {{ $slot }}
        </main>
    </div>

    <script>
        (function () {
            const body = document.body;
            const toggle = document.getElementById('sidebarToggle');
            const storageKey = 'dashboard_sidebar_collapsed';

            if (!toggle) {
                return;
            }

            const mediaQuery = window.matchMedia('(min-width: 1024px)');

            function syncCollapsedState() {
                const savedCollapsed = localStorage.getItem(storageKey) === '1';
                const shouldCollapse = mediaQuery.matches && savedCollapsed;

                body.classList.toggle('sidebar-collapsed', shouldCollapse);
                toggle.setAttribute('aria-expanded', shouldCollapse ? 'false' : 'true');
                toggle.setAttribute('aria-label', shouldCollapse ? 'Expandir menu lateral' : 'Recolher menu lateral');
            }

            toggle.addEventListener('click', function () {
                if (!mediaQuery.matches) {
                    return;
                }

                const willCollapse = !body.classList.contains('sidebar-collapsed');
                localStorage.setItem(storageKey, willCollapse ? '1' : '0');
                syncCollapsedState();
            });

            if (typeof mediaQuery.addEventListener === 'function') {
                mediaQuery.addEventListener('change', syncCollapsedState);
            } else if (typeof mediaQuery.addListener === 'function') {
                mediaQuery.addListener(syncCollapsedState);
            }

            syncCollapsedState();
        })();

        (function () {
            const body = document.body;
            const menu = document.getElementById('accountMenu');
            const trigger = document.getElementById('accountMenuTrigger');
            const panel = document.getElementById('accountMenuPanel');
            const settingsButton = document.getElementById('accountSettingsBtn');
            const themeToggleButton = document.getElementById('themeToggleBtn');
            const themeToggleLabel = document.getElementById('themeToggleLabel');
            const themeStorageKey = 'dashboard_theme';

            if (!menu || !trigger || !panel) {
                return;
            }

            function setMenuState(open) {
                menu.classList.toggle('is-open', open);
                trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
                panel.setAttribute('aria-hidden', open ? 'false' : 'true');
            }

            function applyTheme(theme) {
                const isDark = theme === 'dark';
                body.classList.toggle('dark-mode', isDark);

                if (themeToggleButton) {
                    themeToggleButton.setAttribute('aria-pressed', isDark ? 'true' : 'false');
                }

                if (themeToggleLabel) {
                    themeToggleLabel.textContent = isDark ? 'Modo claro' : 'Modo escuro';
                }

                window.dispatchEvent(new CustomEvent('dashboard-theme-change', {
                    detail: { theme: isDark ? 'dark' : 'light' },
                }));
            }

            const savedTheme = localStorage.getItem(themeStorageKey);
            applyTheme(savedTheme === 'dark' ? 'dark' : 'light');

            trigger.addEventListener('click', function () {
                const isOpen = menu.classList.contains('is-open');
                setMenuState(!isOpen);
            });

            document.addEventListener('click', function (event) {
                if (!menu.contains(event.target)) {
                    setMenuState(false);
                }
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    setMenuState(false);
                }
            });

            if (settingsButton) {
                settingsButton.addEventListener('click', function () {
                    setMenuState(false);

                    if (typeof window.appAlert === 'function') {
                        window.appAlert({
                            icon: 'info',
                            title: 'Configurações',
                            text: 'Tela de configurações será disponibilizada em breve.',
                        });
                    }
                });
            }

            if (themeToggleButton) {
                themeToggleButton.addEventListener('click', function () {
                    const isDark = body.classList.contains('dark-mode');
                    const nextTheme = isDark ? 'light' : 'dark';
                    localStorage.setItem(themeStorageKey, nextTheme);
                    applyTheme(nextTheme);
                    setMenuState(false);
                });
            }
        })();
    </script>

    @include('components.sweetalert')
    @stack('scripts')
</body>
</html>
