<?php

namespace App\Support\Nomus;

use Illuminate\Http\Client\Factory as HttpFactory;
use RuntimeException;

class NomusApiClient
{
    public function __construct(
        private readonly HttpFactory $http,
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listInvoices(int $page = 1, ?string $query = null): array
    {
        $queryParams = [
            'pagina' => max(1, $page),
        ];

        if ($query !== null && trim($query) !== '') {
            $queryParams['query'] = trim($query);
        }

        $payload = $this->request('rest/nfes', $queryParams);

        if (! is_array($payload)) {
            throw new RuntimeException('Resposta invalida da API Nomus para listagem de notas.');
        }

        return array_values(array_filter($payload, static fn ($item): bool => is_array($item)));
    }

    /**
     * @return array<string, mixed>
     */
    public function getInvoice(int $externalId): array
    {
        $payload = $this->request('rest/nfes/'.$externalId);

        if (is_array($payload) && array_is_list($payload)) {
            $first = $payload[0] ?? null;
            if (! is_array($first)) {
                throw new RuntimeException('Resposta invalida da API Nomus ao detalhar a nota fiscal.');
            }

            return $first;
        }

        if (! is_array($payload)) {
            throw new RuntimeException('Resposta invalida da API Nomus ao detalhar a nota fiscal.');
        }

        return $payload;
    }

    /**
     * @return array{arquivo: string, raw: array<string, mixed>}
     */
    public function getInvoiceDanfe(int $externalId): array
    {
        $payload = $this->request('rest/nfes/danfe/'.$externalId);
        if (! is_array($payload)) {
            throw new RuntimeException('Resposta invalida da API Nomus ao obter DANFE.');
        }

        $fileBase64 = $payload['arquivo'] ?? null;
        if (! is_string($fileBase64) || trim($fileBase64) === '') {
            throw new RuntimeException('A API Nomus nao retornou o arquivo da DANFE.');
        }

        return [
            'arquivo' => trim($fileBase64),
            'raw' => $payload,
        ];
    }

    /**
     * @return mixed
     */
    private function request(string $path, array $queryParams = [])
    {
        $baseUrl = rtrim((string) config('services.nomus.location', ''), '/');
        if ($baseUrl === '') {
            throw new RuntimeException('NOMUS_LOCATION nao configurado.');
        }

        $integrationKey = trim((string) config('services.nomus.integration_key', ''));
        if ($integrationKey === '') {
            throw new RuntimeException('NOMUS_INTEGRATION_KEY nao configurado.');
        }

        $authorizationHeader = str_starts_with(strtolower($integrationKey), 'basic ')
            ? $integrationKey
            : 'Basic '.$integrationKey;

        $response = $this->http
            ->acceptJson()
            ->withHeaders([
                'Authorization' => $authorizationHeader,
                'Content-Type' => 'application/json',
            ])
            ->withOptions([
                'verify' => (bool) config('services.nomus.verify_ssl', true),
            ])
            ->timeout((int) config('services.nomus.timeout_seconds', 30))
            ->retry(2, 500, null, false)
            ->get($baseUrl.'/'.ltrim($path, '/'), $queryParams);

        if ($response->unauthorized() || $response->forbidden()) {
            throw new RuntimeException('Falha de autenticacao na API Nomus.');
        }

        if (! $response->successful()) {
            throw new RuntimeException(
                'Falha na API Nomus. HTTP '.$response->status().' para '.$path
            );
        }

        return $response->json();
    }
}
