<?php

namespace Modules\Core\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Core\Entities\BiReport;
use Modules\Core\Entities\FeatureFlag;
use Modules\Core\Entities\ScimToken;
use Modules\Core\Entities\SsoProvider;
use Modules\Core\Entities\WorkflowDefinition;
use Modules\Core\Services\WorkflowRunner;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudioController extends Controller
{
    public const SOURCES = [
        'deals' => ['table' => 'crm_deals', 'columns' => ['id', 'name', 'amount', 'currency_code', 'company_id', 'probability']],
        'leads' => ['table' => 'crm_leads', 'columns' => ['id', 'topic', 'first_name', 'last_name', 'email', 'company', 'lead_score']],
        'tasks' => ['table' => 'prj_tasks', 'columns' => ['id', 'title', 'status', 'priority', 'project_id', 'assignee_id', 'duration_days']],
        'content' => ['table' => 'crm_content_items', 'columns' => ['id', 'title', 'status', 'calendar_id', 'assignee_id']],
        'companies' => ['table' => 'crm_companies', 'columns' => ['id', 'name', 'legal_name', 'economic_code']],
        'projects' => ['table' => 'prj_projects', 'columns' => ['id', 'name', 'status']],
        'calendars' => ['table' => 'crm_content_calendars', 'columns' => ['id', 'name', 'network', 'kind']],
    ];

    public const JOINS = [
        'deals' => ['companies' => ['table' => 'crm_companies', 'local' => 'company_id', 'foreign' => 'id']],
        'tasks' => ['projects' => ['table' => 'prj_projects', 'local' => 'project_id', 'foreign' => 'id']],
        'content' => ['calendars' => ['table' => 'crm_content_calendars', 'local' => 'calendar_id', 'foreign' => 'id']],
    ];

    public function workflows(): JsonResponse
    {
        return response()->json(['data' => WorkflowDefinition::query()->orderByDesc('id')->get()]);
    }

    public function storeWorkflow(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:160',
            'trigger_key' => 'nullable|string|max:64',
            'status' => 'nullable|in:draft,published',
            'graph' => 'required|array',
            'graph.nodes' => 'required|array|min:1',
            'graph.edges' => 'nullable|array',
        ]);
        $row = WorkflowDefinition::query()->create([
            'name' => $data['name'],
            'trigger_key' => $data['trigger_key'] ?? 'manual',
            'status' => $data['status'] ?? 'draft',
            'graph' => $data['graph'],
        ]);

        return response()->json(['data' => $row], 201);
    }

    public function runWorkflow(Request $request, int $id, WorkflowRunner $runner): JsonResponse
    {
        $workflow = WorkflowDefinition::query()->findOrFail($id);
        $context = $request->validate(['context' => 'nullable|array'])['context'] ?? [];
        $context['user_id'] = $context['user_id'] ?? $request->user()->id;
        $run = $runner->run($workflow, $context);

        return response()->json(['data' => $run]);
    }

    public function reports(): JsonResponse
    {
        return response()->json(['data' => BiReport::query()->orderByDesc('id')->get()]);
    }

    public function storeReport(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:160',
            'source' => 'required|in:deals,leads,tasks,content',
            'columns' => 'required|array|min:1',
            'columns.*' => 'string',
            'filters' => 'nullable|array',
            'layout' => 'nullable|array',
            'joins' => 'nullable|array',
            'joins.*.source' => 'required|in:companies,projects,calendars',
            'joins.*.columns' => 'nullable|array',
            'joins.*.columns.*' => 'string',
            'schedule' => 'nullable|array',
            'schedule.frequency' => 'required_with:schedule|in:daily,weekly',
            'schedule.email' => 'required_with:schedule|email',
            'schedule.weekday' => 'nullable|integer|min:0|max:6',
        ]);
        $allowed = self::SOURCES[$data['source']]['columns'];
        $data['columns'] = array_values(array_intersect($data['columns'], $allowed));
        if ($data['columns'] === []) {
            return response()->json(['message' => 'no_columns', 'errors' => ['columns' => ['no_columns']]], 422);
        }
        $report = BiReport::query()->create($data);

        return response()->json(['data' => $report], 201);
    }

    public function runReport(int $id): JsonResponse
    {
        $report = BiReport::query()->findOrFail($id);
        $table = $this->rows($report);

        return response()->json(['data' => $table]);
    }

    public function exportReport(int $id): StreamedResponse
    {
        $report = BiReport::query()->findOrFail($id);
        $table = $this->rows($report);
        $columns = $table['columns'];
        $rows = $table['rows'];

        return response()->streamDownload(function () use ($columns, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $columns);
            foreach ($rows as $row) {
                $line = [];
                foreach ($columns as $column) {
                    $line[] = $row[$column] ?? '';
                }
                fputcsv($out, $line);
            }
            fclose($out);
        }, 'report-'.$report->id.'.csv', ['Content-Type' => 'text/csv']);
    }

    public function flags(): JsonResponse
    {
        return response()->json(['data' => FeatureFlag::query()->orderBy('key')->get()]);
    }

    public function storeFlag(Request $request): JsonResponse
    {
        $data = $request->validate([
            'key' => 'required|string|max:80',
            'enabled' => 'required|boolean',
            'rollout_percent' => 'nullable|integer|min:0|max:100',
            'sandbox_only' => 'nullable|boolean',
            'description' => 'nullable|string|max:200',
        ]);
        $flag = FeatureFlag::query()->updateOrCreate(['key' => $data['key']], $data);

        return response()->json(['data' => $flag]);
    }

    public function evaluate(Request $request): JsonResponse
    {
        $keys = (array) $request->input('keys', []);
        $sandbox = $request->boolean('sandbox') || $request->header('X-Webino-Sandbox') === '1';
        $user = $request->user();
        $out = [];
        foreach ($keys as $key) {
            $flag = FeatureFlag::query()->where('key', $key)->first();
            $enabled = false;
            if ($flag && $flag->enabled && (! $flag->sandbox_only || $sandbox)) {
                $bucket = $user ? ($user->id % 100) : 0;
                $enabled = $bucket < (int) $flag->rollout_percent;
            }
            $out[(string) $key] = $enabled;
        }

        return response()->json(['data' => ['sandbox' => $sandbox, 'flags' => $out]]);
    }

    public function ssoIndex(): JsonResponse
    {
        return response()->json(['data' => SsoProvider::query()->orderBy('id')->get()->map(fn (SsoProvider $p) => $this->ssoResource($p))]);
    }

    public function storeSso(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'protocol' => 'required|in:oidc,saml',
            'enabled' => 'nullable|boolean',
            'client_id' => 'nullable|string',
            'client_secret' => 'nullable|string',
            'issuer' => 'nullable|string',
            'metadata_url' => 'nullable|string',
            'redirect_uri' => 'nullable|string',
        ]);
        $provider = SsoProvider::query()->create($data);

        return response()->json(['data' => $this->ssoResource($provider)], 201);
    }

    public function oidcRedirect(int $id): JsonResponse
    {
        $provider = SsoProvider::query()->where('protocol', 'oidc')->where('enabled', true)->findOrFail($id);
        $state = Str::random(32);
        cache()->put('oidc:'.$state, $provider->id, now()->addMinutes(15));
        $query = http_build_query([
            'client_id' => $provider->client_id,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'redirect_uri' => $provider->redirect_uri ?: url('/api/v1/core/sso/oidc/callback'),
            'state' => $state,
        ]);
        $base = rtrim((string) $provider->issuer, '/');

        return response()->json(['data' => ['url' => $base.'/authorize?'.$query, 'state' => $state]]);
    }

    public function oidcCallback(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => 'required|string', 'state' => 'required|string']);
        $providerId = cache()->pull('oidc:'.$data['state']);
        $provider = SsoProvider::query()->findOrFail($providerId);
        $token = \Illuminate\Support\Facades\Http::asForm()->timeout(20)->post(rtrim((string) $provider->issuer, '/').'/token', [
            'grant_type' => 'authorization_code',
            'code' => $data['code'],
            'client_id' => $provider->client_id,
            'client_secret' => $provider->client_secret,
            'redirect_uri' => $provider->redirect_uri ?: url('/api/v1/core/sso/oidc/callback'),
        ]);
        if (! $token->successful()) {
            return response()->json(['message' => 'oidc_token_failed'], 422);
        }
        $userinfo = \Illuminate\Support\Facades\Http::withToken((string) $token->json('access_token'))
            ->timeout(20)
            ->get(rtrim((string) $provider->issuer, '/').'/userinfo');
        $email = (string) ($userinfo->json('email') ?: '');
        if ($email === '') {
            return response()->json(['message' => 'oidc_email_missing'], 422);
        }
        $user = User::query()->firstOrCreate(
            ['email' => $email],
            ['name' => (string) ($userinfo->json('name') ?: $email), 'password' => Hash::make(Str::random(32))]
        );
        $user->forceFill(['is_active' => true])->save();
        $plain = $user->createToken('oidc')->plainTextToken;

        return response()->json(['data' => ['token' => $plain, 'user' => ['id' => $user->id, 'email' => $user->email]]]);
    }

    public function samlMetadata(int $id)
    {
        SsoProvider::query()->where('protocol', 'saml')->findOrFail($id);
        $xml = app(\Modules\Core\Services\SamlIdpService::class)->metadataXml($id);

        return response($xml, 200)->header('Content-Type', 'application/samlmetadata+xml');
    }

    public function idpSso(Request $request): JsonResponse
    {
        $data = $request->validate([
            'SAMLRequest' => 'required|string',
            'email' => 'nullable|email',
            'password' => 'nullable|string',
        ]);
        $user = \Illuminate\Support\Facades\Auth::guard('sanctum')->user() ?: $request->user();
        if (! $user && ! empty($data['email']) && ! empty($data['password'])) {
            $candidate = User::query()->where('email', $data['email'])->first();
            if ($candidate && Hash::check($data['password'], (string) $candidate->password)) {
                $user = $candidate;
            }
        }
        if (! $user) {
            return response()->json(['message' => 'saml_login_required'], 401);
        }
        $issued = app(\Modules\Core\Services\SamlIdpService::class)->issue($user, $data['SAMLRequest']);

        return response()->json(['data' => [
            'SAMLResponse' => $issued['SAMLResponse'],
            'acs' => $issued['acs'],
        ]]);
    }

    public function samlAcs(Request $request, int $id): JsonResponse
    {
        $provider = SsoProvider::query()->where('protocol', 'saml')->where('enabled', true)->findOrFail($id);
        $raw = (string) $request->input('SAMLResponse', '');
        $xml = $raw;
        $decoded = $raw !== '' ? base64_decode($raw, true) : false;
        if (is_string($decoded) && $decoded !== '' && str_contains($decoded, '<')) {
            $xml = $decoded;
        }
        $previous = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        // Namespaced assertions have no un-namespaced children, so a valid document is falsy.
        if (! $doc instanceof \SimpleXMLElement) {
            return response()->json(['message' => 'saml_invalid'], 422);
        }
        $doc->registerXPathNamespace('saml', 'urn:oasis:names:tc:SAML:2.0:assertion');
        $name = (string) ($doc->xpath('//saml:NameID')[0] ?? '');
        $signed = str_contains($xml, 'Signature');
        if ($signed) {
            if (! app(\Modules\Core\Services\SamlIdpService::class)->verify($xml)) {
                return response()->json(['message' => 'saml_bad_signature'], 422);
            }
        } else {
            $secret = (string) ($doc->xpath('//saml:Attribute[@Name="secret"]/saml:AttributeValue')[0] ?? '');
            if ($name === '' || ($provider->client_secret && ! hash_equals((string) $provider->client_secret, $secret))) {
                return response()->json(['message' => 'saml_rejected'], 422);
            }
        }
        if ($name === '') {
            return response()->json(['message' => 'saml_rejected'], 422);
        }
        $email = str_contains($name, '@') ? $name : $name.'@sso.local';
        $user = User::query()->firstOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => Hash::make(Str::random(32))]
        );
        $user->forceFill(['is_active' => true])->save();

        return response()->json(['data' => ['token' => $user->createToken('saml')->plainTextToken, 'email' => $user->email]]);
    }

    public function issueScim(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => 'required|string|max:80']);
        $plain = Str::random(40);
        ScimToken::query()->create(['name' => $data['name'], 'token_hash' => hash('sha256', $plain)]);

        return response()->json(['data' => ['token' => $plain]], 201);
    }

    public function scimUsers(Request $request): JsonResponse
    {
        if (! $this->scimAuthorized($request)) {
            return response()->json(['detail' => 'unauthorized'], 401);
        }
        $users = User::query()->orderBy('id')->limit(100)->get();

        return response()->json([
            'schemas' => ['urn:ietf:params:scim:api:messages:2.0:ListResponse'],
            'totalResults' => $users->count(),
            'Resources' => $users->map(fn (User $user) => $this->scimUser($user))->values(),
        ]);
    }

    public function scimCreate(Request $request): JsonResponse
    {
        if (! $this->scimAuthorized($request)) {
            return response()->json(['detail' => 'unauthorized'], 401);
        }
        $email = (string) ($request->input('userName') ?: data_get($request->all(), 'emails.0.value'));
        $user = User::query()->firstOrCreate(
            ['email' => $email],
            ['name' => trim((string) $request->input('name.givenName').' '.(string) $request->input('name.familyName')) ?: $email, 'password' => Hash::make(Str::random(24))]
        );
        $user->forceFill(['is_active' => $request->boolean('active', true)])->save();

        return response()->json($this->scimUser($user), 201);
    }

    public function scimPatch(Request $request, int $id): JsonResponse
    {
        if (! $this->scimAuthorized($request)) {
            return response()->json(['detail' => 'unauthorized'], 401);
        }
        $user = User::query()->findOrFail($id);
        foreach ((array) $request->input('Operations', []) as $op) {
            if (! is_array($op)) {
                continue;
            }
            if (($op['path'] ?? '') === 'active') {
                $user->forceFill(['is_active' => filter_var($op['value'] ?? false, FILTER_VALIDATE_BOOLEAN)])->save();
            }
        }

        return response()->json($this->scimUser($user->fresh()));
    }

    public function scimDelete(Request $request, int $id): JsonResponse
    {
        if (! $this->scimAuthorized($request)) {
            return response()->json(['detail' => 'unauthorized'], 401);
        }
        $user = User::query()->findOrFail($id);
        $user->forceFill(['is_active' => false])->save();

        return response()->json([], 204);
    }

    private function scimAuthorized(Request $request): bool
    {
        $header = (string) $request->header('Authorization', '');
        if (! str_starts_with($header, 'Bearer ')) {
            return false;
        }
        $hash = hash('sha256', trim(substr($header, 7)));

        return ScimToken::query()->where('token_hash', $hash)->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function scimUser(User $user): array
    {
        return [
            'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:User'],
            'id' => (string) $user->id,
            'userName' => $user->email,
            'active' => (bool) $user->is_active,
            'name' => ['formatted' => $user->name],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    /**
     * @return array{columns: list<string>, rows: list<array<string, mixed>>}
     */
    private function rows(BiReport $report): array
    {
        $source = self::SOURCES[$report->source];
        $columns = array_values(array_intersect($report->columns ?? [], $source['columns']));
        $joins = array_values(array_filter((array) ($report->joins ?? []), 'is_array'));
        $allowedJoins = self::JOINS[$report->source] ?? [];
        if ($joins === [] || $allowedJoins === []) {
            $query = DB::table($source['table'])->select($columns)->limit(500);
            $this->applyFilters($query, $source['columns'], (array) ($report->filters ?? []), '');

            return ['columns' => $columns, 'rows' => json_decode(json_encode($query->get()), true)];
        }
        $select = [];
        foreach ($columns as $column) {
            $select[] = 'src.'.$column.' as '.$column;
        }
        $query = DB::table($source['table'].' as src');
        $alias = 0;
        foreach ($joins as $join) {
            $target = (string) ($join['source'] ?? '');
            $spec = $allowedJoins[$target] ?? null;
            if (! $spec || ! isset(self::SOURCES[$target])) {
                continue;
            }
            $name = 'j'.$alias;
            $alias++;
            $query->leftJoin($spec['table'].' as '.$name, 'src.'.$spec['local'], '=', $name.'.'.$spec['foreign']);
            foreach ((array) ($join['columns'] ?? []) as $column) {
                if (! in_array($column, self::SOURCES[$target]['columns'], true)) {
                    continue;
                }
                $label = $target.'_'.$column;
                $select[] = $name.'.'.$column.' as '.$label;
                $columns[] = $label;
            }
        }
        $query->select($select)->limit(500);
        $this->applyFilters($query, $source['columns'], (array) ($report->filters ?? []), 'src.');

        return ['columns' => $columns, 'rows' => json_decode(json_encode($query->get()), true)];
    }

    /**
     * @param  list<string>  $allowed
     * @param  list<mixed>  $filters
     */
    private function applyFilters(\Illuminate\Database\Query\Builder $query, array $allowed, array $filters, string $prefix): void
    {
        foreach ($filters as $filter) {
            if (! is_array($filter)) {
                continue;
            }
            $field = (string) ($filter['field'] ?? '');
            if (! in_array($field, $allowed, true)) {
                continue;
            }
            $op = $filter['op'] ?? '=';
            if (! in_array($op, ['=', '>', '<', 'like'], true)) {
                $op = '=';
            }
            $query->where($prefix.$field, $op, $filter['value'] ?? null);
        }
    }

    private function ssoResource(SsoProvider $provider): array
    {
        return [
            'id' => $provider->id,
            'name' => $provider->name,
            'protocol' => $provider->protocol,
            'enabled' => $provider->enabled,
            'client_id' => $provider->client_id,
            'issuer' => $provider->issuer,
            'metadata_url' => $provider->metadata_url,
            'redirect_uri' => $provider->redirect_uri,
            'has_secret' => filled($provider->client_secret),
        ];
    }
}
