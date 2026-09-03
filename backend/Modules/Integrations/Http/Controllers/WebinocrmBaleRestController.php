<?php

namespace Modules\Integrations\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Integrations\Services\BaleBusinessService;
use Modules\Integrations\Services\BaleWebhookHandler;

/**
 * هم‌تراز REST بله در webinocrm (مسیرهای /api/webinocrm/v1/bale/*).
 */
class WebinocrmBaleRestController extends Controller
{
    public function __construct(
        private BaleBusinessService $bale,
        private BaleWebhookHandler $webhookHandler,
    ) {}

    public function getSettings(): JsonResponse
    {
        return response()->json(['data' => $this->bale->getSettingsForGet()]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $body = $request->validate([
            'token' => 'nullable|string|max:512',
            'webhook_secret' => 'nullable|string|max:512',
            'enabled' => 'nullable|boolean',
            'settings' => 'nullable|array',
        ]);

        return response()->json(['data' => $this->bale->updateSettings($body)]);
    }

    public function getLogs(Request $request): JsonResponse
    {
        $limit = max(1, (int) $request->query('limit', 50));

        return response()->json(['data' => ['logs' => $this->bale->latestLogs($limit)]]);
    }

    public function getWebhookUrl(): JsonResponse
    {
        return response()->json([
            'data' => [
                'url' => $this->bale->webhookUrl(),
                'message' => 'این URL را در پنل بله برای ربات خود setWebhook کنید.',
            ],
        ]);
    }

    public function setWebhook(): JsonResponse
    {
        return response()->json(['data' => ['result' => $this->bale->setWebhook()]]);
    }

    public function diagnosticsWebhookInfo(): JsonResponse
    {
        return response()->json(['data' => ['webhook_info' => $this->bale->diagnosticsWebhookInfo()]]);
    }

    public function diagnosticsTestLog(): JsonResponse
    {
        $this->bale->diagnosticsTestLog();

        return response()->json(['data' => ['ok' => true]]);
    }

    public function diagnosticsStats(): JsonResponse
    {
        return response()->json(['data' => $this->bale->diagnosticsStats()]);
    }

    public function sendMessage(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'user_id' => 'required|integer|min:1',
            'message' => 'required|string|max:4096',
        ]);
        $userId = (int) $payload['user_id'];
        $message = trim($payload['message']);
        $user = \App\Models\User::query()->find($userId);
        if (! $user || ! $user->bale_chat_id) {
            return response()->json(['data' => ['message' => 'این کاربر شناسه بله ثبت‌شده ندارد.']], 400);
        }
        $res = $this->bale->sendMessageToUser($userId, $message);
        if ($res === null) {
            return response()->json(['data' => ['message' => 'ارسال ناموفق بود.']], 400);
        }

        return response()->json(['data' => ['result' => $res]]);
    }

    public function sendBulkMessage(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'message' => 'required|string|max:4096',
            'mode' => 'nullable|string|in:all,roles',
            'roles' => 'nullable|array',
            'roles.*' => 'string|max:64',
        ]);
        $message = trim($payload['message']);
        $mode = (string) ($payload['mode'] ?? 'all');
        $roles = isset($payload['roles']) && is_array($payload['roles']) ? array_map('strval', $payload['roles']) : [];
        $res = $this->bale->sendBulkMessage($message, $mode, $roles);
        if ($res === null) {
            return response()->json(['data' => ['message' => 'ارسال انبوه نامعتبر است.']], 400);
        }

        return response()->json(['data' => $res]);
    }

    public function getStats(): JsonResponse
    {
        return response()->json(['data' => $this->bale->getStats()]);
    }

    public function getUserLogs(Request $request): JsonResponse
    {
        $chatId = (string) $request->query('chat_id', '');
        $limit = max(10, min(300, (int) $request->query('limit', 50)));
        $res = $this->bale->getUserLogs($chatId, $limit);
        if ($res === null) {
            return response()->json(['data' => ['message' => 'chat_id الزامی است.']], 400);
        }

        return response()->json(['data' => $res]);
    }

    public function listCampaigns(): JsonResponse
    {
        return response()->json(['data' => ['campaigns' => $this->bale->listCampaigns()]]);
    }

    public function createCampaign(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:191',
            'message' => 'required|string|max:4096',
            'mode' => 'nullable|string|max:32',
            'roles' => 'nullable|array',
            'roles.*' => 'string|max:64',
            'scheduled_at' => 'nullable|date',
        ]);
        $id = $this->bale->createCampaign($data);
        if ($id === null) {
            return response()->json(['data' => ['message' => 'نام کمپین و متن پیام الزامی است.']], 400);
        }

        return response()->json(['data' => ['id' => $id]]);
    }

    public function runCampaign(Request $request, int $id): JsonResponse
    {
        $res = $this->bale->runCampaign($id);
        if ($res === null) {
            return response()->json(['data' => ['message' => 'کمپین یافت نشد.']], 404);
        }

        return response()->json(['data' => $res]);
    }

    public function kpiDashboard(): JsonResponse
    {
        return response()->json(['data' => $this->bale->kpiDashboard()]);
    }

    public function processAutomationQueue(): JsonResponse
    {
        return response()->json(['data' => $this->bale->processAutomationQueue()]);
    }

    public function webhook(Request $request): JsonResponse
    {
        $out = $this->webhookHandler->handle($request);

        return response()->json(
            array_filter([
                'ok' => $out['ok'],
                'duplicate' => $out['duplicate'] ?? null,
                'error' => $out['error'] ?? null,
            ], fn ($v) => $v !== null),
            $out['status']
        );
    }
}
