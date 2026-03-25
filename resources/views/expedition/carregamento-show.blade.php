<x-layouts.app :title="'Carregamento #' . $carregamento->id">
    <section class="panel-card">
        <div class="invoice-detail-top">
            <a href="{{ route('expedition.index', ['etapa' => 'carregamento']) }}" class="page-btn page-btn-light">Voltar</a>
            <div class="filters-actions">
                <button type="button" id="btn-edit-carreg" class="page-btn">Editar</button>
                <form method="POST" action="{{ route('carregamentos.destroy', ['carregamento' => $carregamento->id]) }}" class="inline-delete-form js-carreg-delete" data-serial="Carregamento #{{ $carregamento->id }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="page-btn" style="background:#ef4444;">Remover</button>
                </form>
            </div>
        </div>
    </section>

    <section class="panel-card">
        <h2 class="section-title">Dados do carregamento</h2>
        <div class="invoice-kv-grid">
            <div><strong>NF:</strong> {{ $carregamento->invoice_numero ?? '-' }}</div>
            <div><strong>Motorista:</strong> {{ $carregamento->motorista_nome }}</div>
            <div><strong>Documento:</strong> {{ $carregamento->motorista_documento }}</div>
            <div><strong>Placa:</strong> {{ $carregamento->placa_veiculo }}</div>
            <div><strong>Empresa:</strong> {{ $carregamento->motorista_empresa ?? '-' }}</div>
            <div><strong>Status:</strong> {{ ucfirst($carregamento->status) }}</div>
        </div>
    </section>

    <section class="panel-card">
        <h2 class="section-title">Leitura para conferencia</h2>
        <div>
            <label class="panel-label" for="scan-barcode">Codigo de barras</label>
            <input
                id="scan-barcode"
                type="text"
                class="input"
                placeholder="Leia o codigo de barras do equipamento"
                autocomplete="off"
                autofocus
            >
        </div>
        <div id="scan-feedback" style="margin-top:var(--space-3);display:none;"></div>
    </section>

    <section class="panel-card">
        <div class="invoice-detail-top" style="margin-bottom:var(--space-3);">
            <h2 class="section-title" style="margin:0;">Equipamentos da nota</h2>
            <div class="filters-actions" style="gap:var(--space-3);">
                <span class="panel-label" style="margin:0;">Progresso: <strong id="progress-count">{{ $totalConferidos }}/{{ $totalItems }}</strong></span>
                @if ($totalItems > 0 && $totalConferidos === $totalItems)
                    <form method="POST" action="{{ route('carregamentos.finalizar', ['carregamento' => $carregamento->id]) }}">
                        @csrf
                        <button type="submit" class="page-btn" style="background:#22c55e;">Finalizar carregamento</button>
                    </form>
                @endif
            </div>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Serial</th>
                        <th>Status</th>
                        <th>Conferido</th>
                    </tr>
                </thead>
                <tbody id="items-table-body">
                    @forelse ($items as $item)
                        <tr id="item-row-{{ $item->equipment_id ?? $item->item_id }}" class="{{ $item->conferido ? 'row-conferido' : '' }}">
                            <td>{{ $item->serial_number }}</td>
                            <td>
                                @if ($item->status_name)
                                    <span class="status-badge" style="--status-color: @safeColor($item->status_color)">
                                        {{ $item->status_name }}
                                    </span>
                                @else
                                    <span style="color:var(--muted);">Nao cadastrado</span>
                                @endif
                            </td>
                            <td>
                                @if ($item->conferido)
                                    <span style="color:#16a34a;font-weight:600;">✓ Conferido</span>
                                @else
                                    <span style="color:var(--muted);">Pendente</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="empty-cell">Nenhum equipamento vinculado a esta nota.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- Modal de edicao --}}
    <div id="carreg-edit-modal" class="modal-overlay" style="display:none;">
        <div class="modal-card" style="max-width:480px;">
            <div class="modal-header">
                <h3 class="modal-title">Editar carregamento</h3>
                <button type="button" class="modal-close" id="carreg-edit-close" aria-label="Fechar">&times;</button>
            </div>
            <form method="POST" action="{{ route('carregamentos.update', ['carregamento' => $carregamento->id]) }}" class="stack-16" style="margin-top:var(--space-4);">
                @csrf
                @method('PUT')
                <div>
                    <label class="panel-label" for="edit_motorista_nome">Nome do motorista</label>
                    <input id="edit_motorista_nome" name="motorista_nome" type="text" class="input" required value="{{ $carregamento->motorista_nome }}">
                </div>
                <div>
                    <label class="panel-label" for="edit_motorista_documento">Documento (CPF)</label>
                    <input id="edit_motorista_documento" name="motorista_documento" type="text" class="input" required value="{{ $carregamento->motorista_documento }}" maxlength="14">
                </div>
                <div>
                    <label class="panel-label" for="edit_placa_veiculo">Placa do veiculo</label>
                    <input id="edit_placa_veiculo" name="placa_veiculo" type="text" class="input" required value="{{ $carregamento->placa_veiculo }}" maxlength="8" style="text-transform:uppercase;">
                </div>
                <div>
                    <label class="panel-label" for="edit_motorista_empresa">Empresa do motorista</label>
                    <input id="edit_motorista_empresa" name="motorista_empresa" type="text" class="input" value="{{ $carregamento->motorista_empresa }}">
                </div>
                <div class="filters-actions">
                    <button type="submit" class="page-btn">Salvar</button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script src="{{ asset('js/barcode-converter.js') }}?v={{ filemtime(public_path('js/barcode-converter.js')) }}"></script>
    <script>
        (function () {
            var input = document.getElementById('scan-barcode');
            var feedback = document.getElementById('scan-feedback');
            var progressCount = document.getElementById('progress-count');
            var scanUrl = @json(route('carregamentos.scan', ['carregamento' => $carregamento->id]));
            var csrfToken = document.querySelector('meta[name="csrf-token"]')?.content
                || document.querySelector('input[name="_token"]')?.value
                || @json(csrf_token());

            if (!input) return;

            function showFeedback(type, message) {
                var colors = {
                    success: { bg: '#dcfce7', border: '#22c55e', text: '#166534' },
                    error: { bg: '#fef2f2', border: '#ef4444', text: '#991b1b' },
                    warning: { bg: '#fefce8', border: '#f59e0b', text: '#92400e' },
                };
                var c = colors[type] || colors.error;
                feedback.style.display = '';
                feedback.style.padding = '10px 14px';
                feedback.style.borderRadius = '8px';
                feedback.style.border = '1px solid ' + c.border;
                feedback.style.background = c.bg;
                feedback.style.color = c.text;
                feedback.style.fontWeight = '600';
                feedback.textContent = message;

                if (type === 'success') {
                    setTimeout(function () { feedback.style.display = 'none'; }, 3000);
                }
            }

            function doScan(barcode) {
                fetch(scanUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ barcode: barcode }),
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.ok) {
                        showFeedback('success', data.message);
                        if (progressCount && data.total) {
                            progressCount.textContent = data.conferidos + '/' + data.total;
                        }
                        setTimeout(function () { location.reload(); }, 1500);
                    } else {
                        var type = data.type === 'divergente' ? 'error' : data.type === 'duplicado' ? 'warning' : 'error';
                        showFeedback(type, data.message);
                    }
                })
                .catch(function () {
                    showFeedback('error', 'Erro de conexao ao conferir o equipamento.');
                });
            }

            input.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.keyCode === 13) {
                    e.preventDefault();
                    e.stopPropagation();
                    var val = input.value.trim();
                    if (!val) return;
                    doScan(val);
                    input.value = '';
                }
            });

            input.addEventListener('change', function () {
                var val = input.value.trim();
                if (!val) return;
                doScan(val);
                input.value = '';
            });
        })();

        // Edit modal
        (function () {
            var btn = document.getElementById('btn-edit-carreg');
            var modal = document.getElementById('carreg-edit-modal');
            var closeBtn = document.getElementById('carreg-edit-close');
            if (!btn || !modal) return;
            btn.addEventListener('click', function () { modal.style.display = ''; });
            closeBtn.addEventListener('click', function () { modal.style.display = 'none'; });
            modal.addEventListener('click', function (e) { if (e.target === modal) modal.style.display = 'none'; });
            document.addEventListener('keydown', function (e) { if (e.key === 'Escape') modal.style.display = 'none'; });
        })();

        // Delete confirmation
        (function () {
            document.addEventListener('submit', function (e) {
                var form = e.target.closest('.js-carreg-delete');
                if (!form) return;
                e.preventDefault();
                var label = form.dataset.serial || 'este carregamento';
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Remover carregamento?',
                        html: 'Tem certeza que deseja remover <strong>' + label + '</strong>?',
                        showCancelButton: true,
                        confirmButtonColor: '#ef4444',
                        confirmButtonText: 'Sim, remover',
                        cancelButtonText: 'Cancelar',
                        reverseButtons: true,
                        focusCancel: true,
                    }).then(function (result) {
                        if (result.isConfirmed) HTMLFormElement.prototype.submit.call(form);
                    });
                } else {
                    if (confirm('Remover ' + label + '?')) HTMLFormElement.prototype.submit.call(form);
                }
            });
        })();
    </script>
    @endpush
</x-layouts.app>
