<?php

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Core\Entities\CoreLicense;
use Modules\Core\Entities\CoreLicenseRenewalPrice;
use Modules\Core\Services\LicenseRenewal\LicenseRenewalService;
use Modules\Integrations\Services\Payments\PaymentException;
use Modules\Integrations\Services\Payments\PaymentOrchestrator;
use Symfony\Component\HttpFoundation\Response;

class LicenseRenewalPublicController extends Controller
{
    public function __construct(private LicenseRenewalService $renewals) {}

    /** GET /api/v1/public/license-renewal?domain=&return_url= */
    public function info(Request $request): JsonResponse
    {
        $data = $request->validate([
            'domain' => 'required|string|max:253',
            'return_url' => 'nullable|string|max:1000',
        ]);
        $license = $this->renewals->findByDomain($data['domain']);
        if (! $license) {
            return response()->json(['message' => 'لایسنسی برای این دامنه پیدا نشد.'], 404);
        }

        return response()->json(['data' => $this->renewals->publicInfo($license, $data['return_url'] ?? null)])
            ->header('Cache-Control', 'no-store');
    }

    /** GET /renew/{license} (signed) */
    public function page(Request $request, int $license): Response
    {
        $model = $this->signedLicense($request, $license);
        if ($model instanceof Response) {
            return $model;
        }
        $return = $request->query('return_url');
        $info = $this->renewals->publicInfo($model, is_string($return) ? $return : null);

        return response()->view('license-renewal.page', [
            'info' => $info,
            'error' => $request->query('error'),
            'paid' => $request->query('status') === 'settled',
        ])->header('Cache-Control', 'no-store');
    }

    /** GET /renew/{license}/pay/{option} (signed) — starts payment and redirects to the gateway. */
    public function pay(Request $request, int $license, int $option, PaymentOrchestrator $payments): Response|RedirectResponse
    {
        $model = $this->signedLicense($request, $license);
        if ($model instanceof Response) {
            return $model;
        }
        $price = $this->renewals->options($model)->firstWhere('id', $option);
        if (! $price instanceof CoreLicenseRenewalPrice || ! $this->renewals->isRenewable($model)) {
            return $this->errorPage('این گزینهٔ تمدید دیگر در دسترس نیست.', 422);
        }
        $returnUrl = is_string($request->query('return_url')) ? $request->query('return_url') : null;
        $order = $this->renewals->order($model, $price, $returnUrl);
        $back = $order->return_url ?: $this->renewals->renewUrl($model);
        try {
            $intent = $payments->start([
                'payable_type' => 'license_renewal',
                'payable_id' => (string) $order->id,
                'mode' => 'cash',
                'return_url' => $back,
                'idempotency_key' => 'license_renewal:'.$order->id,
            ]);
        } catch (PaymentException $e) {
            return $this->errorPage($e->getMessage(), 422);
        }

        return redirect()->away((string) $intent->redirect_url);
    }

    private function signedLicense(Request $request, int $id): CoreLicense|Response
    {
        if (! $request->hasValidSignature()) {
            return $this->errorPage('لینک تمدید نامعتبر یا منقضی شده است. لطفاً لینک را دوباره از سایت باز کنید.', 403);
        }
        $license = CoreLicense::query()->find($id);
        if (! $license) {
            return $this->errorPage('لایسنس پیدا نشد.', 404);
        }

        return $license;
    }

    private function errorPage(string $message, int $status): Response
    {
        return response()->view('license-renewal.error', ['message' => $message], $status);
    }
}
