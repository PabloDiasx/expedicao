<x-layouts.app :title="'Carregamento'">
    <section class="panel-card">
        <h2 class="section-title">Carregamento</h2>

        <form method="GET" action="{{ route('expedition.index', ['etapa' => 'carregamento']) }}" class="filters-grid">
            <div>
                <label class="panel-label" for="q">Busca</label>
                <input
                    id="q"
                    name="q"
                    type="text"
                    class="input"
                    value="{{ $search }}"
                    placeholder="Motorista, placa, NF ou empresa"
                    autocomplete="off"
                >
                <input type="hidden" name="etapa" value="carregamento">
            </div>

            <div class="filters-actions">
                <button type="submit" class="page-btn">Buscar</button>
                <a href="{{ route('expedition.index', ['etapa' => 'carregamento']) }}" class="page-btn page-btn-light">Limpar</a>
            </div>
        </form>
    </section>

    <section class="panel-card">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>NF</th>
                        <th>Motorista</th>
                        <th>Placa</th>
                        <th>Empresa</th>
                        <th>Progresso</th>
                        <th>Status</th>
                        <th>Criado em</th>
                        <th>Usuario</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($carregamentos as $c)
                        @php
                            $prog = $progressData[$c->id] ?? ['total' => 0, 'conferidos' => 0];
                            $statusColors = ['aberto' => '#3b82f6', 'concluido' => '#22c55e', 'cancelado' => '#ef4444'];
                        @endphp
                        <tr
                            class="table-row-link"
                            tabindex="0"
                            data-href="{{ route('carregamentos.show', ['carregamento' => $c->id]) }}"
                            role="link"
                            aria-label="Abrir carregamento #{{ $c->id }}"
                        >
                            <td><a href="{{ route('carregamentos.show', ['carregamento' => $c->id]) }}" class="row-link-anchor">{{ $c->invoice_numero ?? '-' }}</a></td>
                            <td>{{ $c->motorista_nome }}</td>
                            <td>{{ $c->placa_veiculo }}</td>
                            <td>{{ $c->motorista_empresa ?? '-' }}</td>
                            <td>{{ $prog['conferidos'] }}/{{ $prog['total'] }}</td>
                            <td>
                                <span class="status-badge" style="--status-color: {{ $statusColors[$c->status] ?? '#64748b' }}">
                                    {{ ucfirst($c->status) }}
                                </span>
                            </td>
                            <td>{{ \Illuminate\Support\Carbon::parse($c->created_at)->format('d/m/Y H:i') }}</td>
                            <td>{{ $c->user_name ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="empty-cell">Nenhum carregamento registrado.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @push('scripts')
    <script src="{{ asset('js/table-row-links.js') }}?v={{ filemtime(public_path('js/table-row-links.js')) }}"></script>
    @endpush
</x-layouts.app>
