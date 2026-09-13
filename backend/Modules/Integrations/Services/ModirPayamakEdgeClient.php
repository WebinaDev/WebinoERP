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
            $data = $this->mockResponse($path, $method, $body, $query);

            return ['ok' => true, 'code' => 200, 'data' => $data, 'meta' => ['current_page' => 1, 'last_page' => 1], 'message' => ''];
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
        return $this->request('GET', 'api/report/by_bulk', [], ['messages_outbox_id' => $outboxId]);
    }

    public function reportInbox(int $page = 1, int $limit = 20, array $filters = []): array
    {
        return $this->request('POST', 'api/report/messages-inbox', ['page' => $page, 'per_page' => $limit, 'filters' => $filters]);
    }

    public function myCredit(): array
    {
        return $this->request('GET', 'api/payment/credit/mine');
    }

    public function listPatterns(array $query = []): array
    {
        return $this->request('GET', 'api/patterns', [], $query);
    }

    public function getPattern(string $code): array
    {
        return $this->request('GET', 'api/patterns/'.rawurlencode($code));
    }

    public function createPattern(array $payload): array
    {
        return $this->request('POST', 'api/patterns/normal', $payload);
    }

    public function updatePattern(string $code, array $payload): array
    {
        return $this->request('PUT', 'api/patterns/'.rawurlencode($code), $payload);
    }

    public function deletePattern(string $code): array
    {
        return $this->request('DELETE', 'api/patterns/'.rawurlencode($code));
    }

    public function listNumbers(array $query = []): array
    {
        return $this->request('GET', 'api/number/numbers', [], $query);
    }

    public function listPhonebooks(array $query = []): array
    {
        return $this->request('GET', 'api/phonebooks/list-new', [], $query);
    }

    public function createPhonebook(array $payload): array
    {
        return $this->request('POST', 'api/phonebooks', $payload);
    }

    public function updatePhonebook(int $id, array $payload): array
    {
        return $this->request('PUT', 'api/phonebooks/'.$id, $payload);
    }

    public function deletePhonebook(int $id): array
    {
        return $this->request('POST', 'api/phonebooks/delete-list', ['listPhonebooks' => [$id]]);
    }

    public function listPhonebookContacts(int $phonebookId, array $query = []): array
    {
        return $this->request('GET', 'api/phonebooks/numbers/contact-list', [], array_merge($query, [
            'phonebook_id' => (string) $phonebookId,
        ]));
    }

    public function createPhonebookContact(int $phonebookId, array $payload): array
    {
        $item = array_merge($payload, ['phonebook_id' => $payload['phonebook_id'] ?? (string) $phonebookId]);

        return $this->request('POST', 'api/phonebooks/numbers/add-list-new', ['list' => [$item]]);
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function mockResponse(string $path, string $method = 'GET', array $body = [], array $query = []): array
    {
        if (str_contains($path, 'api/report/new_list') || str_contains($path, 'api/report/messages-inbox')) {
            return [
                'entries' => [
                    [
                        'id' => 1,
                        'recipient' => '09120000000',
                        'message' => 'Mock message',
                        'status' => 'delivered',
                        'created_at' => now()->toIso8601String(),
                    ],
                ],
            ];
        }

        if (str_contains($path, 'api/report/by_bulk') || str_contains($path, 'api/report/outbox')) {
            return [
                'id' => $query['messages_outbox_id'] ?? 1,
                'recipient' => '09120000000',
                'message' => 'Mock detail',
                'status' => 'delivered',
            ];
        }

        if (str_contains($path, 'api/patterns')) {
            if (preg_match('#api/patterns/([^/]+)$#', $path, $m) && strtoupper($method) !== 'DELETE') {
                return [
                    'code' => urldecode($m[1]),
                    'title' => 'Mock pattern',
                    'message' => 'Hello %name%',
                    'status' => 'active',
                ];
            }

            return [
                'patterns' => [
                    ['code' => 'PAT001', 'title' => 'Welcome', 'message' => 'Hello %name%', 'status' => 'active'],
                    ['code' => 'PAT002', 'title' => 'OTP', 'message' => 'Code %code%', 'status' => 'active'],
                ],
            ];
        }

        if (str_contains($path, 'api/number')) {
            return [
                'numbers' => [
                    ['number' => '+983000505', 'type' => 'service', 'status' => 'active'],
                    ['number' => '+9810002000', 'type' => 'personal', 'status' => 'active'],
                ],
            ];
        }

        if (str_contains($path, 'api/phonebooks')) {
            if (str_contains($path, 'contact-list') || str_contains($path, '/contacts')) {
                return [
                    'entries' => [
                        ['name' => 'Ali', 'number' => '09121111111'],
                    ],
                ];
            }

            return [
                'phonebooks' => [
                    ['id' => 1, 'name' => 'Default book', 'title' => 'Default book'],
                ],
            ];
        }

        if (str_contains($path, 'api/ticket')) {
            if (preg_match('#api/tickets?/(\d+)$#', $path, $m) || str_contains($path, 'api/ticket/show')) {
                return [
                    'ticket' => [
                        'id' => (int) ($m[1] ?? $query['ticket_id'] ?? 1),
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

        if (str_contains($path, 'api/user/draft') || str_contains($path, 'api/drafts')) {
            if (str_contains($path, 'group')) {
                return ['groups' => [['id' => 1, 'name' => 'Default']]];
            }

            return [
                'drafts' => [
                    ['id' => 1, 'title' => 'Mock draft', 'message' => 'Sample message', 'created_at' => now()->toIso8601String()],
                ],
            ];
        }

        if (str_contains($path, 'api/user')) {
            if (preg_match('#api/user/(\d+)$#', $path, $m) || str_contains($path, 'api/user/show')) {
                return [
                    'user' => [
                        'id' => (int) ($m[1] ?? $query['user_id'] ?? 1),
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

        if (str_contains($path, 'api/send')) {
            return ['id' => 'mock-send-1', 'cost' => 500, 'status' => 'queued'];
        }

        if (str_contains($path, 'api/payment/credit')) {
            return ['credit' => 1000000, 'expire' => now()->addMonth()->toDateString()];
        }

        return ['mock' => true, 'path' => $path, 'body' => $body];
    }
}
