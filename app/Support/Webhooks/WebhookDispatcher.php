<?php

namespace App\Support\Webhooks;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class WebhookDispatcher
{
    /**
     * Available events and their fields.
     */
    public const EVENTS = [
        'entrada_registrada' => [
            'label' => 'Entrada registrada',
            'description' => 'Quando um equipamento dá entrada no sistema',
            'fields' => [
                'serial_number' => 'Número de série',
                'barcode' => 'Código de barras original',
                'model_name' => 'Modelo do equipamento',
                'status' => 'Status atual',
                'invoice_number' => 'Número da NF',
                'customer_name' => 'Nome do cliente',
                'destination' => 'Destino',
                'user_name' => 'Usuário que registrou',
                'timestamp' => 'Data e hora',
            ],
        ],
        'status_alterado' => [
            'label' => 'Status alterado',
            'description' => 'Quando o status de um equipamento muda',
            'fields' => [
                'serial_number' => 'Número de série',
                'model_name' => 'Modelo do equipamento',
                'from_status' => 'Status anterior',
                'to_status' => 'Novo status',
                'event_source' => 'Origem da alteração',
                'user_name' => 'Usuário',
                'notes' => 'Observação',
                'timestamp' => 'Data e hora',
            ],
        ],
        'carregamento_criado' => [
            'label' => 'Carregamento criado',
            'description' => 'Quando um novo carregamento é aberto',
            'fields' => [
                'carregamento_id' => 'ID do carregamento',
                'invoice_number' => 'Número da NF',
                'motorista_nome' => 'Nome do motorista',
                'motorista_documento' => 'Documento do motorista',
                'placa_veiculo' => 'Placa do veículo',
                'motorista_empresa' => 'Empresa do motorista',
                'total_items' => 'Total de itens',
                'user_name' => 'Usuário',
                'timestamp' => 'Data e hora',
            ],
        ],
        'carregamento_finalizado' => [
            'label' => 'Carregamento finalizado',
            'description' => 'Quando todos os itens são conferidos e o carregamento é concluído',
            'fields' => [
                'carregamento_id' => 'ID do carregamento',
                'invoice_number' => 'Número da NF',
                'motorista_nome' => 'Nome do motorista',
                'placa_veiculo' => 'Placa do veículo',
                'total_items' => 'Total de itens conferidos',
                'user_name' => 'Usuário',
                'timestamp' => 'Data e hora',
            ],
        ],
        'equipamento_conferido' => [
            'label' => 'Equipamento conferido no carregamento',
            'description' => 'Quando um equipamento é escaneado e conferido durante o carregamento',
            'fields' => [
                'serial_number' => 'Número de série',
                'carregamento_id' => 'ID do carregamento',
                'invoice_number' => 'Número da NF',
                'barcode_scanned' => 'Código de barras lido',
                'user_name' => 'Usuário',
                'timestamp' => 'Data e hora',
            ],
        ],
        'nf_vinculada' => [
            'label' => 'Nota fiscal vinculada',
            'description' => 'Quando uma NF é vinculada automaticamente a um equipamento',
            'fields' => [
                'serial_number' => 'Número de série',
                'invoice_number' => 'Número da NF',
                'customer_name' => 'Nome do cliente',
                'destination' => 'Destino',
                'timestamp' => 'Data e hora',
            ],
        ],
    ];

    /**
     * Dispatch a webhook event to all configured integrations.
     */
    public static function dispatch(int $tenantId, string $event, array $allData): void
    {
        $integrations = DB::table('integrations')
            ->where('tenant_id', $tenantId)
            ->where('type', 'webhook')
            ->get();

        foreach ($integrations as $integration) {
            try {
                $config = is_string($integration->webhook_config)
                    ? json_decode($integration->webhook_config, true)
                    : (is_array($integration->webhook_config) ? $integration->webhook_config : []);

                if (empty($config)) {
                    continue;
                }

                $eventConfig = $config[$event] ?? null;
                if (! $eventConfig || ! ($eventConfig['enabled'] ?? false)) {
                    continue;
                }

                // Filter only selected fields
                $selectedFields = $eventConfig['fields'] ?? [];
                $payload = [
                    'evento' => $event,
                    'tenant_id' => $tenantId,
                ];

                foreach ($selectedFields as $field) {
                    if (array_key_exists($field, $allData)) {
                        $payload[$field] = $allData[$field];
                    }
                }

                self::send($integration, $payload);
            } catch (Throwable $e) {
                Log::warning('[WebhookDispatcher] Erro ao enviar webhook ' . $event . ' para ' . $integration->name . ': ' . $e->getMessage());
            }
        }
    }

    private static function send(object $integration, array $payload): void
    {
        $client = Http::timeout((int) $integration->timeout_seconds)
            ->withOptions(['verify' => (bool) $integration->verify_ssl])
            ->acceptJson()
            ->asJson();

        if ($integration->auth_type === 'basic') {
            $authHeader = str_starts_with(strtolower((string) $integration->auth_value), 'basic ')
                ? (string) $integration->auth_value
                : 'Basic ' . (string) $integration->auth_value;
            $client = $client->withHeaders(['Authorization' => $authHeader]);
        } elseif ($integration->auth_type === 'bearer') {
            $client = $client->withToken((string) $integration->auth_value);
        } elseif ($integration->auth_type === 'api_key') {
            $client = $client->withHeaders(['X-API-Key' => (string) $integration->auth_value]);
        }

        $client->post(rtrim((string) $integration->base_url, '/'), $payload);
    }
}
