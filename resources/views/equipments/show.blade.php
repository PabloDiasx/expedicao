<x-layouts.app :title="'Detalhe do Equipamento'">
    <section class="panel-card">
        <div class="invoice-detail-top">
            <h2 class="section-title">Equipamento {{ $equipment->serial_number }}</h2>
            <a href="{{ route('equipments.index') }}" class="page-btn page-btn-light">Voltar</a>
        </div>

        <div class="invoice-kv-grid">
            <div><strong>Modelo:</strong> {{ $equipment->model_name }}</div>
            <div><strong>Codigo de barras:</strong> {{ $equipment->barcode }}</div>
            <div>
                <strong>Status:</strong>
                <span class="status-badge" style="--status-color: {{ $equipment->status_color }}">
                    {{ $equipment->status_name }}
                </span>
            </div>
            <div><strong>Setor:</strong> {{ $equipment->sector_name ?? '-' }}</div>
            <div><strong>Fabricado em:</strong> {{ $equipment->manufactured_at ? \Illuminate\Support\Carbon::parse($equipment->manufactured_at)->format('d/m/Y') : '-' }}</div>
            <div><strong>Montado em:</strong> {{ $equipment->assembled_at ? \Illuminate\Support\Carbon::parse($equipment->assembled_at)->format('d/m/Y') : '-' }}</div>
            <div><strong>Atualizado em:</strong> {{ \Illuminate\Support\Carbon::parse($equipment->updated_at)->format('d/m/Y H:i') }}</div>
        </div>
    </section>

    <section class="panel-card">
        <h2 class="section-title">Dados da Nota Fiscal (Entrada)</h2>

        <div class="invoice-kv-grid">
            <div><strong>Numero da NF:</strong> {{ $equipment->entry_invoice_number ?? '-' }}</div>
            <div><strong>Cliente:</strong> {{ $equipment->entry_customer_name ?? '-' }}</div>
            <div><strong>Destino:</strong> {{ $equipment->entry_destination ?? '-' }}</div>
            <div><strong>ID da NF na Nomus:</strong> {{ $equipment->entry_invoice_external_id ?? '-' }}</div>
            <div><strong>Vinculada em:</strong> {{ $equipment->entry_invoice_linked_at ? \Illuminate\Support\Carbon::parse($equipment->entry_invoice_linked_at)->format('d/m/Y H:i') : '-' }}</div>
            <div><strong>Ultima atualizacao na Nomus:</strong> {{ $equipment->invoice_nomus_updated_at ? \Illuminate\Support\Carbon::parse($equipment->invoice_nomus_updated_at)->format('d/m/Y H:i') : '-' }}</div>
        </div>

        @if ($equipment->entry_invoice_id)
            <p class="muted">
                <a href="{{ route('invoices.show', ['invoice' => $equipment->entry_invoice_id]) }}">Abrir nota fiscal completa</a>
            </p>
        @endif
    </section>

    <section class="panel-card">
        <h2 class="section-title">Historico de movimentacoes</h2>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Data/Hora</th>
                        <th>Status</th>
                        <th>Setor</th>
                        <th>Usuario</th>
                        <th>Observacao</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentTransitions as $transition)
                        <tr>
                            <td>{{ \Illuminate\Support\Carbon::parse($transition->changed_at)->format('d/m/Y H:i') }}</td>
                            <td>
                                <span class="status-badge" style="--status-color: {{ $transition->status_color }}">
                                    {{ $transition->status_name }}
                                </span>
                            </td>
                            <td>{{ $transition->sector_name ?? '-' }}</td>
                            <td>{{ $transition->user_name ?? '-' }}</td>
                            <td>{{ $transition->notes ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="empty-cell">Nenhuma movimentacao encontrada.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.app>

