<?php

namespace Modules\Crm\Http\Controllers\Customer360;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\CustomerAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Crm\Entities\CrmAccount;
use Modules\Crm\Services\Customer360\CustomerPortalService;
use Modules\Integrations\Services\Payments\PaymentException;

/**
 * Customer portal (PHASE5_API.md §3). Mounted under the existing /api/v1/projects/portal/* prefix
 * so it shares the projects portal auth (sanctum + client role linked via crm_account_users).
 */
class CustomerPortalHubController extends Controller
{
    public function __construct(private CustomerAccess $customers, private CustomerPortalService $portal) {}

    public function profile(Request $r): JsonResponse
    {
        [$u, $a] = $this->ctx($r);

        return response()->json(['data' => $this->portal->profile($u, $a)]);
    }

    public function contracts(Request $r): JsonResponse
    {
        [, $a] = $this->ctx($r);

        return response()->json(['data' => $this->portal->contracts($a)]);
    }

    public function contract(Request $r, int $id): JsonResponse
    {
        [, $a] = $this->ctx($r);

        return response()->json(['data' => $this->portal->contract($a, $id)]);
    }

    public function signOtp(Request $r, int $id): JsonResponse
    {
        [$u, $a] = $this->ctx($r);

        return response()->json(['data' => $this->portal->sendOtp($u, $a, $id)]);
    }

    public function sign(Request $r, int $id): JsonResponse
    {
        [$u, $a] = $this->ctx($r);
        $v = $r->validate(['code' => 'required|string|max:10', 'accept' => 'accepted']);

        return response()->json(['data' => $this->portal->sign($u, $a, $id, $v['code'], $r->ip(), $r->userAgent())]);
    }

    public function invoices(Request $r): JsonResponse
    {
        [, $a] = $this->ctx($r);
        $status = in_array($r->query('status'), ['open', 'paid', 'all'], true) ? $r->query('status') : 'all';
        [$rows, $kpis] = $this->portal->invoices($a, $status);

        return response()->json(['data' => $rows, 'meta' => ['kpis' => $kpis]]);
    }

    public function pay(Request $r, int $id): JsonResponse
    {
        [$u, $a] = $this->ctx($r);
        $v = $r->validate(['gateway' => 'nullable|string|max:40', 'return_url' => 'nullable|url|max:1000']);
        try {
            return response()->json(['data' => $this->portal->pay($u, $a, $id, $v['gateway'] ?? null, $v['return_url'] ?? null)]);
        } catch (PaymentException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => $e->errorCode], $e->status);
        }
    }

    public function payment(Request $r, string $paymentId): JsonResponse
    {
        [$u, $a] = $this->ctx($r);

        return response()->json(['data' => $this->portal->payment($u, $a, $paymentId)]);
    }

    public function services(Request $r): JsonResponse
    {
        [, $a] = $this->ctx($r);
        [$rows, $domains] = $this->portal->services($a);

        return response()->json(['data' => $rows, 'meta' => ['domains' => $domains]]);
    }

    public function documents(Request $r): JsonResponse
    {
        [$u, $a] = $this->ctx($r);

        return response()->json(['data' => $this->portal->documents($u, $a)]);
    }

    /** @return array{0: User, 1: CrmAccount} */
    private function ctx(Request $r): array
    {
        $u = $r->user();
        abort_unless($u && $this->customers->isPortalCustomer($u), 403);
        $id = $r->query('account_id');

        return [$u, $this->portal->account($u, is_numeric($id) ? (int) $id : null)];
    }
}
