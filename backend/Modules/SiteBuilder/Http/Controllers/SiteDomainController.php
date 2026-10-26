<?php

namespace Modules\SiteBuilder\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Platform\Support\TenantAliasDomains;
use Modules\SiteBuilder\Entities\WebinoSiteDomain;
use Modules\SiteBuilder\Entities\WebinoSiteProvision;
use Modules\SiteBuilder\Services\SiteProvisionOrchestrator;
use Modules\SiteBuilder\Support\SiteDomainCaddy;
use Throwable;

class SiteDomainController extends Controller
{
    public function index(WebinoSiteProvision $siteProvision): JsonResponse
    {
        WebinoSiteDomain::ensurePrimary($siteProvision);

        return response()->json(['data' => $this->rows($siteProvision)]);
    }

    public function store(Request $request, WebinoSiteProvision $siteProvision, SiteProvisionOrchestrator $orchestrator): JsonResponse
    {
        WebinoSiteDomain::ensurePrimary($siteProvision);
        $data = $this->validated($request, null);
        $row = WebinoSiteDomain::query()->create($data + ['provision_id' => $siteProvision->id]);

        return $this->respond($siteProvision, $orchestrator, 201, $row);
    }

    public function update(Request $request, WebinoSiteProvision $siteProvision, WebinoSiteDomain $domain, SiteProvisionOrchestrator $orchestrator): JsonResponse
    {
        abort_unless((int) $domain->provision_id === (int) $siteProvision->id, 404);
        $data = $this->validated($request, $domain);
        if ($domain->role === WebinoSiteDomain::ROLE_PRIMARY) {
            // primary host/role change goes through the change-domain flow
            $data = array_intersect_key($data, array_flip(['include_www']));
        }
        $domain->update($data);

        return $this->respond($siteProvision, $orchestrator, 200, $domain);
    }

    public function destroy(WebinoSiteProvision $siteProvision, WebinoSiteDomain $domain, SiteProvisionOrchestrator $orchestrator): JsonResponse
    {
        abort_unless((int) $domain->provision_id === (int) $siteProvision->id, 404);
        if ($domain->role === WebinoSiteDomain::ROLE_PRIMARY) {
            return response()->json(['message' => 'دامنه اصلی را نمی‌توان حذف کرد.'], 422);
        }
        $domain->delete();

        return $this->respond($siteProvision, $orchestrator, 200);
    }

    /**
     * @return array<string,mixed>
     */
    private function validated(Request $request, ?WebinoSiteDomain $existing): array
    {
        $request->merge(['domain' => $request->has('domain')
            ? TenantAliasDomains::normalizeHost((string) $request->input('domain'))
            : $existing?->domain]);
        $data = $request->validate([
            'domain' => [
                'required', 'string', 'max:253',
                'regex:/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/',
                Rule::unique('webino_site_domains', 'domain')->ignore($existing?->id),
            ],
            'role' => [$existing ? 'sometimes' : 'required', Rule::in([WebinoSiteDomain::ROLE_ALIAS, WebinoSiteDomain::ROLE_REDIRECT])],
            'include_www' => 'sometimes|boolean',
            'redirect_path' => 'nullable|string|max:255',
            'redirect_status' => 'nullable|integer|in:301,302,307,308',
            'preserve_path' => 'sometimes|boolean',
        ], [
            'domain.unique' => 'این دامنه قبلا برای یک سایت ثبت شده است.',
            'domain.regex' => 'دامنه معتبر نیست.',
        ]);
        // also block hosts that are another site's primary (not yet backfilled rows)
        $owner = WebinoSiteProvision::query()->whereRaw('lower(domain) = ?', [$data['domain']])->first();
        if ($owner && (! $existing || (int) $owner->id !== (int) $existing->provision_id) && (int) $owner->id !== (int) $request->route('siteProvision')?->id) {
            abort(response()->json(['message' => 'این دامنه دامنه اصلی سایت دیگری است: '.$owner->slug, 'errors' => ['domain' => ['این دامنه دامنه اصلی سایت دیگری است.']]], 422));
        }
        if (array_key_exists('redirect_path', $data)) {
            $data['redirect_path'] = SiteDomainCaddy::normalizePath($data['redirect_path']);
        }
        if (array_key_exists('redirect_status', $data) && $data['redirect_status'] === null) {
            $data['redirect_status'] = 301;
        }

        return $data;
    }

    private function respond(WebinoSiteProvision $p, SiteProvisionOrchestrator $orchestrator, int $code, ?WebinoSiteDomain $row = null): JsonResponse
    {
        $apply = ['ok' => true, 'applied' => false, 'log' => 'site not live yet'];
        if (in_array($p->status, [WebinoSiteProvision::STATUS_READY, WebinoSiteProvision::STATUS_SSL_PENDING], true)) {
            try {
                $apply = $orchestrator->applySiteDomains($p);
            } catch (Throwable $e) {
                $apply = ['ok' => false, 'applied' => false, 'log' => $e->getMessage()];
            }
        }

        return response()->json([
            'data' => $this->rows($p),
            'meta' => ['caddy' => $apply, 'item' => $row?->fresh()],
        ], $code);
    }

    private function rows(WebinoSiteProvision $p): array
    {
        return WebinoSiteDomain::query()->where('provision_id', $p->id)
            ->orderByRaw("CASE role WHEN 'primary' THEN 0 WHEN 'alias' THEN 1 ELSE 2 END")->orderBy('id')
            ->get()->toArray();
    }
}
