<x-layouts.app :title="'Configurações'">
    {{-- Abas --}}
    <div class="config-tabs">
        <a href="{{ route('configuracoes.index', ['tab' => 'usuarios']) }}" class="config-tab {{ $tab === 'usuarios' ? 'config-tab--active' : '' }}">Usuarios</a>
        <a href="{{ route('configuracoes.index', ['tab' => 'empresa']) }}" class="config-tab {{ $tab === 'empresa' ? 'config-tab--active' : '' }}">Empresa</a>
        @if ($currentRole->atLeast(\App\Enums\UserRole::Admin))
            <a href="{{ route('configuracoes.index', ['tab' => 'assinatura']) }}" class="config-tab {{ $tab === 'assinatura' ? 'config-tab--active' : '' }}">Assinatura</a>
            <a href="{{ route('configuracoes.index', ['tab' => 'integracoes']) }}" class="config-tab {{ $tab === 'integracoes' ? 'config-tab--active' : '' }}">Integrações</a>
        @endif
    </div>

    {{-- ═══════════════ ABA USUARIOS ═══════════════ --}}
    @if ($tab === 'usuarios')
    <section class="panel-card">
        <div class="invoice-detail-top" style="margin-bottom:var(--space-3);">
            <h2 class="section-title" style="margin:0;">Usuários</h2>
            <button type="button" id="btn-novo-usuario" class="page-btn">Novo usuário</button>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Email</th>
                        <th>Usuario</th>
                        <th>Cargo</th>
                        <th>Criado em</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        @php
                            $role = \App\Enums\UserRole::tryFrom($user->role) ?? \App\Enums\UserRole::Operator;
                            $roleColors = ['ceo' => '#7c3aed', 'admin' => '#2563eb', 'supervisor' => '#0d9488', 'operator' => '#64748b'];
                            $perms = \Illuminate\Support\Facades\DB::table('user_permissions')->where('user_id', $user->id)->get()->keyBy('module');
                        @endphp
                        <tr>
                            <td>{{ $user->name }}</td>
                            <td>{{ $user->email }}</td>
                            <td>{{ $user->username ?? '-' }}</td>
                            <td>
                                <span class="status-badge" style="--status-color: {{ $roleColors[$user->role] ?? '#64748b' }}">
                                    {{ $role->label() }}
                                </span>
                            </td>
                            <td>{{ \Illuminate\Support\Carbon::parse($user->created_at)->format('d/m/Y') }}</td>
                            <td>
                                <div class="filters-actions" style="gap:var(--space-1);">
                                    <button type="button" class="btn-delete-icon js-edit-user" data-id="{{ $user->id }}" data-name="{{ $user->name }}" data-email="{{ $user->email }}" data-username="{{ $user->username }}" data-role="{{ $user->role }}" title="Editar" style="color:var(--page-btn);">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    </button>
                                    <button type="button" class="btn-delete-icon js-perms-user" data-id="{{ $user->id }}" data-name="{{ $user->name }}" data-perms='@json($perms)' title="Permissoes">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                    </button>
                                    @if ((int) $user->id !== (int) auth()->id() && $user->role !== 'ceo')
                                    <form method="POST" action="{{ route('configuracoes.users.destroy', ['user' => $user->id]) }}" class="inline-delete-form js-user-delete" data-serial="{{ $user->name }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-delete-icon" title="Remover">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                        </button>
                                    </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty-cell">Nenhum usuario cadastrado.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- Modal criar usuario --}}
    <div id="create-user-modal" class="modal-overlay" style="display:none;">
        <div class="modal-card" style="max-width:480px;">
            <div class="modal-header">
                <h3 class="modal-title">Novo usuario</h3>
                <button type="button" class="modal-close" id="create-user-close">&times;</button>
            </div>
            <form method="POST" action="{{ route('configuracoes.users.store') }}" class="stack-16" style="margin-top:var(--space-4);">
                @csrf
                <div><label class="panel-label">Nome</label><input name="name" type="text" class="input" required maxlength="191" placeholder="Nome completo"></div>
                <div><label class="panel-label">Email</label><input name="email" type="email" class="input" required maxlength="191" placeholder="email@exemplo.com"></div>
                <div><label class="panel-label">Usuario (opcional)</label><input name="username" type="text" class="input" maxlength="80" placeholder="Login alternativo"></div>
                <div><label class="panel-label">Senha</label><input name="password" type="password" class="input" required minlength="6" placeholder="Minimo 6 caracteres"></div>
                <div><label class="panel-label">Cargo</label><select name="role" class="chart-select" required>@foreach ($availableRoles as $rv => $rl)<option value="{{ $rv }}">{{ $rl }}</option>@endforeach</select></div>
                <div class="filters-actions"><button type="submit" class="page-btn">Criar</button></div>
            </form>
        </div>
    </div>

    {{-- Modal editar usuario --}}
    <div id="edit-user-modal" class="modal-overlay" style="display:none;">
        <div class="modal-card" style="max-width:480px;">
            <div class="modal-header">
                <h3 class="modal-title">Editar usuario</h3>
                <button type="button" class="modal-close" id="edit-user-close">&times;</button>
            </div>
            <form id="edit-user-form" method="POST" class="stack-16" style="margin-top:var(--space-4);">
                @csrf
                @method('PUT')
                <div><label class="panel-label">Nome</label><input id="edit-user-name" name="name" type="text" class="input" required maxlength="191"></div>
                <div><label class="panel-label">Email</label><input id="edit-user-email" name="email" type="email" class="input" required maxlength="191"></div>
                <div><label class="panel-label">Usuario (opcional)</label><input id="edit-user-username" name="username" type="text" class="input" maxlength="80"></div>
                <div><label class="panel-label">Nova senha (deixe vazio para manter)</label><input name="password" type="password" class="input" minlength="6"></div>
                <div><label class="panel-label">Cargo</label><select id="edit-user-role" name="role" class="chart-select" required>@foreach ($availableRoles as $rv => $rl)<option value="{{ $rv }}">{{ $rl }}</option>@endforeach</select></div>
                <div class="filters-actions"><button type="submit" class="page-btn">Salvar</button></div>
            </form>
        </div>
    </div>

    {{-- Modal permissoes --}}
    <div id="perms-modal" class="modal-overlay" style="display:none;">
        <div class="modal-card" style="max-width:720px;">
            <div class="modal-header">
                <h3 class="modal-title">Permissoes — <span id="perms-user-name"></span></h3>
                <button type="button" class="modal-close" id="perms-close">&times;</button>
            </div>
            <form id="perms-form" method="POST" class="stack-16" style="margin-top:var(--space-4);">
                @csrf
                @method('PUT')
                <div class="table-wrap" style="max-height:none;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Modulo</th>
                                <th style="text-align:center;">Ver</th>
                                <th style="text-align:center;">Criar</th>
                                <th style="text-align:center;">Editar</th>
                                <th style="text-align:center;">Remover</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($modules as $mk => $ml)
                                <tr>
                                    <td>{{ $ml }}</td>
                                    <td style="text-align:center;"><input type="checkbox" name="perm_{{ $mk }}[]" value="view" class="perm-cb" data-module="{{ $mk }}" data-action="view"></td>
                                    <td style="text-align:center;"><input type="checkbox" name="perm_{{ $mk }}[]" value="create" class="perm-cb" data-module="{{ $mk }}" data-action="create"></td>
                                    <td style="text-align:center;"><input type="checkbox" name="perm_{{ $mk }}[]" value="edit" class="perm-cb" data-module="{{ $mk }}" data-action="edit"></td>
                                    <td style="text-align:center;"><input type="checkbox" name="perm_{{ $mk }}[]" value="delete" class="perm-cb" data-module="{{ $mk }}" data-action="delete"></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="filters-actions"><button type="submit" class="page-btn">Salvar permissoes</button></div>
            </form>
        </div>
    </div>
    @endif

    {{-- ═══════════════ ABA EMPRESA ═══════════════ --}}
    @if ($tab === 'empresa')
    <section class="panel-card">
        <h2 class="section-title">Dados da empresa</h2>
        <form method="POST" action="{{ route('configuracoes.company.update') }}" class="stack-16">
            @csrf
            @method('PUT')
            <div class="form-grid-2">
                <div>
                    <label class="panel-label">Nome da empresa</label>
                    <input name="name" type="text" class="input" required value="{{ $tenantData->name ?? '' }}" maxlength="150">
                </div>
                <div>
                    <label class="panel-label">Razao social</label>
                    <input name="razao_social" type="text" class="input" value="{{ $tenantData->razao_social ?? '' }}" maxlength="200">
                </div>
            </div>
            <div class="form-grid-3">
                <div>
                    <label class="panel-label">CNPJ</label>
                    <input id="cnpj-input" name="cnpj" type="text" class="input" value="{{ $tenantData->cnpj ?? '' }}" maxlength="20" placeholder="00.000.000/0000-00">
                </div>
                <div>
                    <label class="panel-label">Telefone</label>
                    <input id="telefone-input" name="telefone" type="text" class="input" value="{{ $tenantData->telefone ?? '' }}" maxlength="20" placeholder="(00) 00000-0000">
                </div>
                <div>
                    <label class="panel-label">Email</label>
                    <input name="email" type="email" class="input" value="{{ $tenantData->email ?? '' }}" maxlength="191">
                </div>
            </div>
            <div>
                <label class="panel-label">Endereco</label>
                <input name="endereco" type="text" class="input" value="{{ $tenantData->endereco ?? '' }}" maxlength="300">
            </div>
            <div class="form-grid-3">
                <div>
                    <label class="panel-label">Cidade</label>
                    <input name="cidade" type="text" class="input" value="{{ $tenantData->cidade ?? '' }}" maxlength="100">
                </div>
                <div>
                    <label class="panel-label">Estado</label>
                    <input name="estado" type="text" class="input" value="{{ $tenantData->estado ?? '' }}" maxlength="2" placeholder="SP" style="text-transform:uppercase;">
                </div>
                <div>
                    <label class="panel-label">CEP</label>
                    <input id="cep-input" name="cep" type="text" class="input" value="{{ $tenantData->cep ?? '' }}" maxlength="10" placeholder="00000-000">
                </div>
            </div>
            <div class="filters-actions">
                <button type="submit" class="page-btn">Salvar</button>
            </div>
        </form>
    </section>
    @endif

    {{-- ═══════════════ ABA ASSINATURA ═══════════════ --}}
    @if ($tab === 'assinatura')
    <section class="panel-card">
        <h2 class="section-title">Plano atual</h2>
        @if ($subscription)
            <div class="invoice-kv-grid">
                <div><strong>Plano:</strong> {{ $subscription->plan_label }}</div>
                <div><strong>Valor:</strong> R$ {{ number_format((float) $subscription->price, 2, ',', '.') }}/{{ $subscription->billing_cycle === 'yearly' ? 'ano' : 'mes' }}</div>
                <div><strong>Status:</strong>
                    <span class="status-badge" style="--status-color: {{ $subscription->status === 'active' ? '#22c55e' : '#ef4444' }}">
                        {{ $subscription->status === 'active' ? 'Ativo' : ucfirst($subscription->status) }}
                    </span>
                </div>
                <div><strong>Max usuarios:</strong> {{ $subscription->max_users }}</div>
                <div><strong>Max equipamentos:</strong> {{ $subscription->max_equipments }}</div>
                <div><strong>Integrações:</strong> {{ $subscription->has_integrations ? 'Sim' : 'Nao' }}</div>
                <div><strong>Inicio:</strong> {{ $subscription->started_at ? \Illuminate\Support\Carbon::parse($subscription->started_at)->format('d/m/Y') : '-' }}</div>
                <div><strong>Expira em:</strong> {{ $subscription->expires_at ? \Illuminate\Support\Carbon::parse($subscription->expires_at)->format('d/m/Y') : 'Sem expiração' }}</div>
            </div>
        @else
            <p style="color:var(--muted);">Nenhuma assinatura ativa. Entre em contato para ativar um plano.</p>
        @endif
    </section>

    <section class="panel-card">
        <h2 class="section-title">Planos disponíveis</h2>
        <div class="config-plans-grid">
            <div class="config-plan-card">
                <h3 class="config-plan-name">Starter</h3>
                <p class="config-plan-price">R$ 99<span>/mes</span></p>
                <ul class="config-plan-features">
                    <li>5 usuarios</li>
                    <li>500 equipamentos</li>
                    <li>Suporte por email</li>
                </ul>
            </div>
            <div class="config-plan-card config-plan-card--highlight">
                <h3 class="config-plan-name">Business</h3>
                <p class="config-plan-price">R$ 249<span>/mes</span></p>
                <ul class="config-plan-features">
                    <li>15 usuarios</li>
                    <li>5.000 equipamentos</li>
                    <li>Integrações</li>
                    <li>Relatorios</li>
                    <li>Suporte prioritario</li>
                </ul>
            </div>
            <div class="config-plan-card">
                <h3 class="config-plan-name">Enterprise</h3>
                <p class="config-plan-price">Sob consulta</p>
                <ul class="config-plan-features">
                    <li>Usuarios ilimitados</li>
                    <li>Equipamentos ilimitados</li>
                    <li>Todas integracoes</li>
                    <li>API dedicada</li>
                    <li>Suporte 24/7</li>
                </ul>
            </div>
        </div>
    </section>
    @endif

    {{-- ═══════════════ ABA INTEGRAÇÕES ═══════════════ --}}
    @if ($tab === 'integracoes')
    <section class="panel-card">
        <div class="invoice-detail-top" style="margin-bottom:var(--space-3);">
            <h2 class="section-title" style="margin:0;">Integrações conectadas</h2>
            <button type="button" id="btn-nova-integracao" class="page-btn">Nova integração</button>
        </div>

        <div class="config-integrations-grid">
            @forelse ($integrations as $integ)
                @php
                    $statusColors = ['connected' => '#22c55e', 'disconnected' => '#64748b', 'error' => '#ef4444'];
                    $typeLabels = ['erp' => 'ERP', 'api' => 'API', 'webhook' => 'Webhook'];
                @endphp
                <div class="config-integ-card">
                    <div class="config-integ-header">
                        <div>
                            <h3 class="config-integ-name">{{ $integ->name }}</h3>
                            <span class="status-badge" style="--status-color: {{ $statusColors[$integ->status] ?? '#64748b' }}">
                                {{ $integ->status === 'connected' ? 'Conectado' : ($integ->status === 'error' ? 'Erro' : 'Desconectado') }}
                            </span>
                        </div>
                        <div class="filters-actions" style="gap:var(--space-1);">
                            <button type="button" class="page-btn page-btn-light js-test-integ" data-id="{{ $integ->id }}" style="padding:6px 12px;font-size:var(--text-xs);">Testar</button>
                            <button type="button" class="btn-delete-icon js-edit-integ"
                                data-id="{{ $integ->id }}"
                                data-name="{{ $integ->name }}"
                                data-description="{{ $integ->description }}"
                                data-type="{{ $integ->type }}"
                                data-base-url="{{ $integ->base_url }}"
                                data-auth-type="{{ $integ->auth_type }}"
                                data-auth-value="{{ $integ->auth_value }}"
                                data-verify-ssl="{{ $integ->verify_ssl ? '1' : '0' }}"
                                data-timeout="{{ $integ->timeout_seconds }}"
                                data-config='@json(is_string($integ->webhook_config) ? json_decode($integ->webhook_config, true) : $integ->webhook_config)'
                                title="Editar" style="color:var(--page-btn);">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </button>
                            @if (! $integ->is_native)
                            <form method="POST" action="{{ route('configuracoes.integrations.destroy', ['integration' => $integ->id]) }}" class="inline-delete-form js-integ-delete" data-serial="{{ $integ->name }}">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-delete-icon" title="Remover">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                </button>
                            </form>
                            @endif
                        </div>
                    </div>
                    @if ($integ->description)
                        <p class="config-integ-desc">{{ $integ->description }}</p>
                    @endif
                    <div class="config-integ-details">
                        <span>URL: {{ $integ->base_url ?? '-' }}</span>
                        <span>Auth: {{ ucfirst($integ->auth_type ?? 'none') }}</span>
                        @if ($integ->last_tested_at)
                            <span>Último teste: {{ \Illuminate\Support\Carbon::parse($integ->last_tested_at)->format('d/m/Y H:i') }}</span>
                        @endif
                    </div>
                    <div id="test-result-{{ $integ->id }}" style="display:none;margin-top:var(--space-2);"></div>
                </div>
            @empty
                <p style="color:var(--muted);">Nenhuma integração configurada.</p>
            @endforelse
        </div>
    </section>

    {{-- Modal unificado de integração --}}
    @php $webhookEvents = \App\Support\Webhooks\WebhookDispatcher::EVENTS; @endphp
    <div id="integ-modal" class="modal-overlay" style="display:none;">
        <div class="modal-card" style="max-width:700px;">
            <div class="modal-header">
                <h3 class="modal-title" id="integ-modal-title">Nova integração</h3>
                <button type="button" class="modal-close" id="integ-modal-close">&times;</button>
            </div>

            {{-- Seletor de tipo --}}
            <div class="config-tabs" style="margin-top:var(--space-3);">
                <a href="#" class="config-tab config-tab--active js-type-tab" data-type="api">API</a>
                <a href="#" class="config-tab js-type-tab" data-type="webhook">Webhook</a>
                <a href="#" class="config-tab js-type-tab" data-type="erp">ERP</a>
            </div>

            <form id="integ-form" method="POST" class="stack-16" style="margin-top:var(--space-4);">
                @csrf
                <input type="hidden" id="integ-method" name="_method" value="POST">
                <input type="hidden" id="integ-type" name="type" value="api">

                {{-- Campos comuns --}}
                <div><label class="panel-label">Nome</label><input id="if-name" name="name" type="text" class="input" required maxlength="150" placeholder="Ex: Meu Sistema"></div>
                <div><label class="panel-label">Descrição</label><input id="if-description" name="description" type="text" class="input" maxlength="500" placeholder="Opcional"></div>
                <div><label class="panel-label">URL Base</label><input id="if-base-url" name="base_url" type="url" class="input" required placeholder="https://url.exemplo.com"></div>
                <div class="form-grid-2">
                    <div><label class="panel-label">Autenticação</label><select id="if-auth-type" name="auth_type" class="chart-select" required><option value="bearer">Bearer Token</option><option value="basic">Basic Auth</option><option value="api_key">API Key</option><option value="none">Nenhuma</option></select></div>
                    <div><label class="panel-label">Token / Chave</label><input id="if-auth-value" name="auth_value" type="text" class="input" placeholder="Cole aqui"></div>
                </div>
                <div class="form-grid-2">
                    <div><label class="panel-label">Timeout (segundos)</label><input id="if-timeout" name="timeout_seconds" type="number" class="input" value="30" min="5" max="120"></div>
                    <div><label class="panel-label">Verificar SSL</label><select id="if-verify-ssl" name="verify_ssl" class="chart-select"><option value="1">Sim</option><option value="0">Não</option></select></div>
                </div>

                {{-- Seção Eventos (só aparece para Webhook) --}}
                <div id="integ-events-section" style="display:none;">
                    <h3 style="margin:var(--space-3) 0 var(--space-2);font-size:var(--text-md);font-weight:var(--fw-semibold);">Eventos do webhook</h3>
                    <p style="color:var(--muted);font-size:var(--text-sm);margin:0 0 var(--space-3);">Selecione quais eventos disparam o webhook e quais informações enviar.</p>
                    @foreach ($webhookEvents as $eventKey => $eventDef)
                        <div class="wc-event-block">
                            <label class="wc-event-toggle">
                                <input type="checkbox" name="events[{{ $eventKey }}][enabled]" value="1" class="wc-event-cb" data-event="{{ $eventKey }}">
                                <strong>{{ $eventDef['label'] }}</strong>
                                <span style="color:var(--muted);font-size:var(--text-xs);margin-left:var(--space-2);">{{ $eventDef['description'] }}</span>
                            </label>
                            <div class="wc-fields-grid" id="wc-fields-{{ $eventKey }}" style="display:none;">
                                @foreach ($eventDef['fields'] as $fieldKey => $fieldLabel)
                                    <label class="wc-field-item">
                                        <input type="checkbox" name="events[{{ $eventKey }}][fields][]" value="{{ $fieldKey }}">
                                        {{ $fieldLabel }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="filters-actions"><button type="submit" class="page-btn" id="integ-submit-btn">Criar integração</button></div>
            </form>
        </div>
    </div>

    @endif

    @push('scripts')
    <script>
        (function () {
            var routeBase = @json(route('configuracoes.users.update', ['user' => '___UID___']));
            var permsRouteBase = @json(route('configuracoes.users.permissions', ['user' => '___UID___']));

            function setupModal(btnId, modalId, closeId) {
                var btn = document.getElementById(btnId);
                var modal = document.getElementById(modalId);
                var close = document.getElementById(closeId);
                if (btn) btn.addEventListener('click', function () { modal.style.display = ''; });
                if (close) close.addEventListener('click', function () { modal.style.display = 'none'; });
                if (modal) modal.addEventListener('click', function (e) { if (e.target === modal) modal.style.display = 'none'; });
            }

            setupModal('btn-novo-usuario', 'create-user-modal', 'create-user-close');

            var editModal = document.getElementById('edit-user-modal');
            var editForm = document.getElementById('edit-user-form');
            var editClose = document.getElementById('edit-user-close');
            if (editClose) editClose.addEventListener('click', function () { editModal.style.display = 'none'; });
            if (editModal) editModal.addEventListener('click', function (e) { if (e.target === editModal) editModal.style.display = 'none'; });

            document.addEventListener('click', function (e) {
                var btn = e.target.closest('.js-edit-user');
                if (!btn || !editForm) return;
                editForm.action = routeBase.replace('___UID___', btn.dataset.id);
                document.getElementById('edit-user-name').value = btn.dataset.name;
                document.getElementById('edit-user-email').value = btn.dataset.email;
                document.getElementById('edit-user-username').value = btn.dataset.username || '';
                document.getElementById('edit-user-role').value = btn.dataset.role;
                editModal.style.display = '';
            });

            var permsModal = document.getElementById('perms-modal');
            var permsForm = document.getElementById('perms-form');
            var permsClose = document.getElementById('perms-close');
            if (permsClose) permsClose.addEventListener('click', function () { permsModal.style.display = 'none'; });
            if (permsModal) permsModal.addEventListener('click', function (e) { if (e.target === permsModal) permsModal.style.display = 'none'; });

            document.addEventListener('click', function (e) {
                var btn = e.target.closest('.js-perms-user');
                if (!btn || !permsForm) return;
                permsForm.action = permsRouteBase.replace('___UID___', btn.dataset.id);
                document.getElementById('perms-user-name').textContent = btn.dataset.name;
                permsForm.querySelectorAll('.perm-cb').forEach(function (cb) { cb.checked = false; });
                var perms = {};
                try { perms = JSON.parse(btn.dataset.perms); } catch (err) {}
                Object.keys(perms).forEach(function (mod) {
                    var p = perms[mod];
                    if (p.can_view) { var cb = permsForm.querySelector('[data-module="' + mod + '"][data-action="view"]'); if (cb) cb.checked = true; }
                    if (p.can_create) { var cb = permsForm.querySelector('[data-module="' + mod + '"][data-action="create"]'); if (cb) cb.checked = true; }
                    if (p.can_edit) { var cb = permsForm.querySelector('[data-module="' + mod + '"][data-action="edit"]'); if (cb) cb.checked = true; }
                    if (p.can_delete) { var cb = permsForm.querySelector('[data-module="' + mod + '"][data-action="delete"]'); if (cb) cb.checked = true; }
                });
                permsModal.style.display = '';
            });

            document.addEventListener('submit', function (e) {
                var form = e.target.closest('.js-user-delete');
                if (!form) return;
                e.preventDefault();
                var name = form.dataset.serial || 'este usuario';
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'warning', title: 'Remover usuario?', html: 'Tem certeza que deseja remover <strong>' + name + '</strong>?', showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: 'Sim, remover', cancelButtonText: 'Cancelar', reverseButtons: true, focusCancel: true }).then(function (result) { if (result.isConfirmed) HTMLFormElement.prototype.submit.call(form); });
                } else { if (confirm('Remover ' + name + '?')) HTMLFormElement.prototype.submit.call(form); }
            });

            document.addEventListener('keydown', function (e) { if (e.key === 'Escape') document.querySelectorAll('.modal-overlay').forEach(function (m) { m.style.display = 'none'; }); });

            // ── Masks (empresa) ──
            var cnpjInput = document.getElementById('cnpj-input');
            if (cnpjInput) {
                cnpjInput.addEventListener('input', function () {
                    var v = this.value.replace(/\D/g, '').substring(0, 14);
                    if (v.length > 12) v = v.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{1,2})/, '$1.$2.$3/$4-$5');
                    else if (v.length > 8) v = v.replace(/(\d{2})(\d{3})(\d{3})(\d{1,4})/, '$1.$2.$3/$4');
                    else if (v.length > 5) v = v.replace(/(\d{2})(\d{3})(\d{1,3})/, '$1.$2.$3');
                    else if (v.length > 2) v = v.replace(/(\d{2})(\d{1,3})/, '$1.$2');
                    this.value = v;
                });
            }

            var telInput = document.getElementById('telefone-input');
            if (telInput) {
                telInput.addEventListener('input', function () {
                    var v = this.value.replace(/\D/g, '').substring(0, 11);
                    if (v.length > 6) v = v.replace(/(\d{2})(\d{5})(\d{1,4})/, '($1) $2-$3');
                    else if (v.length > 2) v = v.replace(/(\d{2})(\d{1,5})/, '($1) $2');
                    this.value = v;
                });
            }

            var cepInput = document.getElementById('cep-input');
            if (cepInput) {
                cepInput.addEventListener('input', function () {
                    var v = this.value.replace(/\D/g, '').substring(0, 8);
                    if (v.length > 5) v = v.replace(/(\d{5})(\d{1,3})/, '$1-$2');
                    this.value = v;
                });
            }
            // ── Integrações — Modal unificado ──
            var integModal = document.getElementById('integ-modal');
            var integForm = document.getElementById('integ-form');
            var integClose = document.getElementById('integ-modal-close');
            var integTitle = document.getElementById('integ-modal-title');
            var integSubmitBtn = document.getElementById('integ-submit-btn');
            var integMethodInput = document.getElementById('integ-method');
            var integTypeInput = document.getElementById('integ-type');
            var eventsSection = document.getElementById('integ-events-section');
            var storeUrl = @json(route('configuracoes.integrations.store'));
            var updateUrlBase = @json(route('configuracoes.integrations.update', ['integration' => '___IID___']));
            var csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || @json(csrf_token());

            if (integClose) integClose.addEventListener('click', function () { integModal.style.display = 'none'; });
            if (integModal) integModal.addEventListener('click', function (e) { if (e.target === integModal) integModal.style.display = 'none'; });

            // Type tabs inside modal
            function setActiveType(type) {
                integTypeInput.value = type;
                document.querySelectorAll('.js-type-tab').forEach(function (t) {
                    t.classList.toggle('config-tab--active', t.dataset.type === type);
                });
                eventsSection.style.display = type === 'webhook' ? '' : 'none';
            }

            document.addEventListener('click', function (e) {
                var tab = e.target.closest('.js-type-tab');
                if (tab) { e.preventDefault(); setActiveType(tab.dataset.type); }
            });

            // Toggle event fields — click on card toggles open/close
            document.querySelectorAll('.wc-event-block').forEach(function (block) {
                var toggle = block.querySelector('.wc-event-toggle');
                var cb = block.querySelector('.wc-event-cb');
                if (!toggle || !cb) return;

                toggle.addEventListener('click', function (e) {
                    // If clicking directly on the checkbox, let it handle itself
                    if (e.target === cb) return;
                    e.preventDefault();

                    var eventKey = cb.dataset.event;
                    var fieldsDiv = document.getElementById('wc-fields-' + eventKey);
                    if (!fieldsDiv) return;

                    // Toggle visibility without changing checkbox
                    var isOpen = fieldsDiv.style.display !== 'none';
                    fieldsDiv.style.display = isOpen ? 'none' : '';
                });

                // Checkbox change still controls enable/disable
                cb.addEventListener('change', function () {
                    var fieldsDiv = document.getElementById('wc-fields-' + this.dataset.event);
                    if (fieldsDiv && this.checked) fieldsDiv.style.display = '';
                });
            });

            function resetIntegForm() {
                integForm.reset();
                integForm.querySelectorAll('input[type="checkbox"]').forEach(function (cb) { cb.checked = false; });
                integForm.querySelectorAll('.wc-fields-grid').forEach(function (div) { div.style.display = 'none'; });
                document.getElementById('if-timeout').value = '30';
                document.getElementById('if-verify-ssl').value = '1';
            }

            function fillEventsFromConfig(config) {
                if (!config) return;
                Object.keys(config).forEach(function (eventKey) {
                    var ec = config[eventKey];
                    if (!ec) return;
                    var cb = integForm.querySelector('.wc-event-cb[data-event="' + eventKey + '"]');
                    if (cb && ec.enabled) {
                        cb.checked = true;
                        var fd = document.getElementById('wc-fields-' + eventKey);
                        if (fd) fd.style.display = '';
                    }
                    (ec.fields || []).forEach(function (f) {
                        var fcb = integForm.querySelector('input[name="events[' + eventKey + '][fields][]"][value="' + f + '"]');
                        if (fcb) fcb.checked = true;
                    });
                });
            }

            // "Nova integração" button
            var btnNova = document.getElementById('btn-nova-integracao');
            if (btnNova) {
                btnNova.addEventListener('click', function () {
                    resetIntegForm();
                    integForm.action = storeUrl;
                    integMethodInput.value = 'POST';
                    integTitle.textContent = 'Nova integração';
                    integSubmitBtn.textContent = 'Criar integração';
                    setActiveType('api');
                    integModal.style.display = '';
                });
            }

            // "Editar" button
            document.addEventListener('click', function (e) {
                var btn = e.target.closest('.js-edit-integ');
                if (!btn) return;
                resetIntegForm();
                integForm.action = updateUrlBase.replace('___IID___', btn.dataset.id);
                integMethodInput.value = 'PUT';
                integTitle.textContent = 'Editar integração';
                integSubmitBtn.textContent = 'Salvar';
                document.getElementById('if-name').value = btn.dataset.name || '';
                document.getElementById('if-description').value = btn.dataset.description || '';
                document.getElementById('if-base-url').value = btn.dataset.baseUrl || '';
                document.getElementById('if-auth-type').value = btn.dataset.authType || 'none';
                document.getElementById('if-auth-value').value = btn.dataset.authValue || '';
                document.getElementById('if-verify-ssl').value = btn.dataset.verifySsl || '1';
                document.getElementById('if-timeout').value = btn.dataset.timeout || '30';
                setActiveType(btn.dataset.type || 'api');
                var config = {};
                try { config = JSON.parse(btn.dataset.config) || {}; } catch (err) {}
                fillEventsFromConfig(config);
                integModal.style.display = '';
            });

            // Test connection
            var testRouteBase = @json(route('configuracoes.integrations.test', ['integration' => '___IID___']));
            document.addEventListener('click', function (e) {
                var btn = e.target.closest('.js-test-integ');
                if (!btn) return;
                var id = btn.dataset.id;
                var resultDiv = document.getElementById('test-result-' + id);
                btn.textContent = 'Testando...';
                btn.disabled = true;
                fetch(testRouteBase.replace('___IID___', id), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    credentials: 'same-origin',
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    btn.textContent = 'Testar'; btn.disabled = false;
                    if (resultDiv) {
                        resultDiv.style.display = ''; resultDiv.style.padding = '8px 12px'; resultDiv.style.borderRadius = '6px'; resultDiv.style.fontSize = 'var(--text-sm)'; resultDiv.style.fontWeight = '600';
                        resultDiv.style.background = data.ok ? '#dcfce7' : '#fef2f2'; resultDiv.style.color = data.ok ? '#166534' : '#991b1b';
                        resultDiv.textContent = data.message;
                        setTimeout(function () { resultDiv.style.display = 'none'; }, 5000);
                    }
                    if (data.ok) setTimeout(function () { location.reload(); }, 2000);
                })
                .catch(function () { btn.textContent = 'Testar'; btn.disabled = false; });
            });

            // Delete integration
            document.addEventListener('submit', function (e) {
                var form = e.target.closest('.js-integ-delete');
                if (!form) return;
                e.preventDefault();
                var name = form.dataset.serial || 'esta integração';
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'warning', title: 'Remover integração?', html: 'Tem certeza que deseja remover <strong>' + name + '</strong>?', showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: 'Sim, remover', cancelButtonText: 'Cancelar', reverseButtons: true, focusCancel: true }).then(function (result) { if (result.isConfirmed) HTMLFormElement.prototype.submit.call(form); });
                } else { if (confirm('Remover ' + name + '?')) HTMLFormElement.prototype.submit.call(form); }
            });
        })();
    </script>
    @endpush
</x-layouts.app>
