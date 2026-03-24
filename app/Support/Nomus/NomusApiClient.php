<?php

namespace App\Support\Nomus;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;
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

        return $this->normalizeListPayload($payload, 'listagem de notas');
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
     * @return array<int, array<string, mixed>>
     */
    public function listSalesOrders(int $page = 1, ?string $query = null): array
    {
        $queryParams = [
            'pagina' => max(1, $page),
        ];

        if ($query !== null && trim($query) !== '') {
            $queryParams['query'] = trim($query);
        }

        $payload = $this->request('rest/pedidos', $queryParams);

        return $this->normalizeListPayload($payload, 'listagem de pedidos de venda');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listProducts(int $page = 1, ?string $query = null): array
    {
        $queryParams = [
            'pagina' => max(1, $page),
        ];

        if ($query !== null && trim($query) !== '') {
            $queryParams['query'] = trim($query);
        }

        $payload = $this->request('rest/produtos', $queryParams);

        return $this->normalizeListPayload($payload, 'listagem de produtos');
    }

    /**
     * @return array<string, mixed>
     */
    public function getProduct(int $externalId): array
    {
        $payload = $this->request('rest/produtos/'.$externalId);

        if (is_array($payload) && array_is_list($payload)) {
            $first = $payload[0] ?? null;
            if (! is_array($first)) {
                throw new RuntimeException('Resposta invalida da API Nomus ao detalhar o produto.');
            }

            return $first;
        }

        if (! is_array($payload)) {
            throw new RuntimeException('Resposta invalida da API Nomus ao detalhar o produto.');
        }

        return $payload;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listProductComponents(int $page = 1, ?string $query = null): array
    {
        $queryParams = [
            'pagina' => max(1, $page),
        ];

        if ($query !== null && trim($query) !== '') {
            $queryParams['query'] = trim($query);
        }

        $payload = $this->request('rest/componentesListaMateriais', $queryParams);

        return $this->normalizeListPayload($payload, 'listagem de componentes de lista de materiais');
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

        $client = $this->http
            ->acceptJson()
            ->withHeaders([
                'Authorization' => $authorizationHeader,
                'Content-Type' => 'application/json',
            ])
            ->withOptions([
                'verify' => (bool) config('services.nomus.verify_ssl', true),
            ])
            ->timeout((int) config('services.nomus.timeout_seconds', 30));

        $maxAttempts = 6;
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $response = $client->get($baseUrl.'/'.ltrim($path, '/'), $queryParams);

            if ($response->status() === 429) {
                if ($attempt === $maxAttempts) {
                    throw new RuntimeException('Falha na API Nomus. HTTP 429 para '.$path);
                }

                $waitSeconds = $this->extractThrottleWaitSeconds($response);
                sleep($waitSeconds);

                continue;
            }

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

        throw new RuntimeException('Falha na API Nomus para '.$path.'. Numero maximo de tentativas excedido.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizeListPayload(mixed $payload, string $context): array
    {
        if (! is_array($payload)) {
            throw new RuntimeException('Resposta invalida da API Nomus para '.$context.'.');
        }

        return array_values(array_filter($payload, static fn ($item): bool => is_array($item)));
    }

    private function extractThrottleWaitSeconds(Response $response): int
    {
        $seconds = 2;
        $json = $response->json();

        if (is_array($json) && isset($json['tempoAteLiberar']) && is_numeric($json['tempoAteLiberar'])) {
            $seconds = (int) $json['tempoAteLiberar'];
        }

        return max(1, min(60, $seconds));
    }
}
