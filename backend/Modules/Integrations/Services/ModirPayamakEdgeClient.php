<?php

namespace Modules\Integrations\Services;

use Illuminate\Support\Facades\Http;
use Modules\Integrations\Entities\IntegrationSetting;

class ModirPayamakEdgeClient
{
    private const BASE_URL = 'https://edge.ippanel.com/v1';

    public function isConfigured(): bool
    {
        return $this->apiKey() !== '' && IntegrationSetting::getString('modirpayamak', 'enabled', '0') === '1';
    }

    public function apiKey(): string
    {
        return IntegrationSetting::getString('modirpayamak', 'api_key', env('MODIRPAYAMAK_API_KEY', ''));
    }

    public function defaultFrom(): string
    {
        $from = IntegrationSetting::getString('modirpayamak', 'default_from', '');

        return $from !== '' ? $from : '+983000505';
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, mixed>  $query
     * @return array{ok: bool, code: int, data: mixed, meta: mixed, message: string}
     */
    public function request(string $method, string $path, array $body = [], array $query = []): array
    {
        if (env('MODIRPAYAMAK_MOCK', false)) {
            $data = $this->mockResponse($path, $body);

            return ['ok' => true, 'code' => 200, 'data' => $data, 'meta' => [], 'message' => ''];
        }

        $key = $this->apiKey();
        if ($key === '') {
            return ['ok' => false, 'code' => 503, 'data' => null, 'meta' => [], 'message' => 'ModirPayamak API key is not configured.'];
        }

        $url = rtrim(self::BASE_URL, '/').'/'.ltrim($path, '/');
        $req = Http::timeout(45)
            ->withHeaders([
                'Authorization' => $key,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ]);

        $response = match (strtoupper($method)) {
            'GET' => $req->get($url, $query),
            'POST' => $req->post($url, $body),
            'PUT' => $req->put($url, $body),
            'PATCH' => $req->patch($url, $body),
            'DELETE' => $req->delete($url, $body),
            default => $req->send($method, $url, ['json' => $body]),
        };

        $json = $response->json();
        $data = is_array($json) ? ($json['data'] ?? null) : null;
        $meta = is_array($json) ? ($json['meta'] ?? []) : [];
        $message = is_array($meta) && ! empty($meta['message']) ? (string) $meta['message'] : '';
        $ok = $response->successful() && (! is_array($meta) || ! isset($meta['status']) || $meta['status']);

        return ['ok' => $ok, 'code' => $response->status(), 'data' => $data, 'meta' => $meta, 'message' => $message];
    }

    public function send(array $payload): array
    {
        return $this->request('POST', 'api/send', $payload);
    }

    public function sendWebservice(string $from, string $message, array $recipients, ?string $sendTime = null): array
    {
        $body = [
            'sending_type' => 'webservice',
            'from_number' => $from,
            'message' => $message,
            'params' => ['recipients' => array_values($recipients)],
        ];
        if ($sendTime) {
            $body['send_time'] = $sendTime;
        }

        return $this->send($body);
    }

    public function sendPattern(string $from, string $code, array $recipients, array $params): array
    {
        return $this->send([
            'sending_type' => 'pattern',
            'from_number' => $from,
            'code' => $code,
            'recipients' => array_values($recipients),
            'params' => $params,
        ]);
    }

    public function sendPeerToPeer(array $groups, string $from): array
    {
        return $this->send([
            'sending_type' => 'peer_to_peer',
            'from_number' => $from,
            'params' => ['groups' => $groups],
        ]);
    }

    public function calculatePrice(array $payload): array
    {
        return $this->request('POST', 'api/send/calculate-price', $payload);
    }

    public function reportOutbox(int $page = 1, int $limit = 20, array $filters = []): array
    {
        return $this->request('POST', 'api/report/new_list', ['page' => $page, 'limit' => $limit, 'filters' => $filters]);
    }

    public function reportOutboxById(string $outboxId): array
    {
        return $this->request('GET', 'api/report/outbox/'.rawurlencode($outboxId));
    }

    public function reportInbox(int $page = 1, int $limit = 20, array $filters = []): array
    {
        return $this->request('POST', 'api/report/inbox', ['page' => $page, 'limit' => $limit, 'filters' => $filters]);
    }

    public function myCredit(): array
    {
        return $this->request('GET', 'api/payment/credit/mine');
    }

    public function listPatterns(array $query = []): array
    {
        return $this->request('GET', 'api/patterns', [], $query);
    }

    public function listNumbers(array $query = []): array
    {
        return $this->request('GET', 'api/numbers', [], $query);
    }

    public function listPhonebooks(array $query = []): array
    {
        return $this->request('GET', 'api/phonebooks', [], $query);
    }

    public function createPhonebook(array $payload): array
    {
        return $this->request('POST', 'api/phonebooks', $payload);
    }

    public function listPhonebookContacts(int $phonebookId, array $query = []): array
    {
        return $this->request('GET', 'api/phonebooks/'.$phonebookId.'/contacts', [], $query);
    }

    public function createPhonebookContact(int $phonebookId, array $payload): array
    {
        return $this->request('POST', 'api/phonebooks/'.$phonebookId.'/contacts', $payload);
    }

    /**
     * @return array<string, mixed>
     */
    private function mockResponse(string $path, array $body): array
    {
        if (str_contains($path, 'api/tickets')) {
            if (preg_match('#api/tickets/(\d+)$#', $path, $m)) {
                return [
                    'ticket' => [
                        'id' => (int) $m[1],
                        'title' => 'Mock ticket',
                        'status' => 'open',
                        'messages' => [],
                    ],
                ];
            }

            return [
                'tickets' => [
                    ['id' => 1, 'title' => 'Mock ticket', 'status' => 'open', 'created_at' => now()->toIso8601String()],
                ],
            ];
        }

        if (str_contains($path, 'api/user')) {
            if (preg_match('#api/user/(\d+)$#', $path, $m)) {
                return [
                    'user' => [
                        'id' => (int) $m[1],
                        'username' => 'mock_user',
                        'email' => 'mock@example.com',
                        'status' => 'active',
                    ],
                ];
            }

            return [
                'users' => [
                    ['id' => 1, 'username' => 'mock_user', 'email' => 'mock@example.com', 'status' => 'active'],
                ],
            ];
        }

        if (str_contains($path, 'api/drafts')) {
            if (preg_match('#api/drafts/(\d+)$#', $path, $m)) {
                return [
                    'draft' => [
                        'id' => (int) $m[1],
                        'title' => 'Mock draft',
                        'message' => 'Sample message',
                    ],
                ];
            }

            return [
                'drafts' => [
                    ['id' => 1, 'title' => 'Mock draft', 'message' => 'Sample message', 'created_at' => now()->toIso8601String()],
                ],
            ];
        }

        return ['mock' => true, 'path' => $path, 'body' => $body];
    }
}
