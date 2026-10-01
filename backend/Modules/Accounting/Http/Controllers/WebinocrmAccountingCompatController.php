<?php

namespace Modules\Accounting\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Http\Controllers\Concerns\VerifiesWebinocrmLicenseSignature;
use Modules\Core\Services\CoreLicenseResolver;

/**
 * Live ledger readout for Dashboard tenants (HMAC license auth).
 * Entitlement: domain (+ product). license_key ignored for lookup.
 * GET|POST /api/webinocrm/v1/accounting/ledger
 */
class WebinocrmAccountingCompatController extends Controller
{
    use VerifiesWebinocrmLicenseSignature;

    public function ledger(Request $request): JsonResponse
    {
        if (! $this->verifyLicenseRequest($request)) {
            return response()->json(['error' => ['code' => 'INVALID_SIGNATURE', 'message' => 'Invalid signature']], 403);
        }

        $domain = CoreLicenseResolver::normalizeDomain((string) $request->input('domain', ''));
        $product = CoreLicenseResolver::normalizeProduct($request->input('product'));
        $license = CoreLicenseResolver::find($domain, $product);
        if (! $license) {
            return response()->json(['error' => ['code' => 'LICENSE_NOT_FOUND', 'message' => 'License not found']], 404);
        }

        if (! DB::getSchemaBuilder()->hasTable('acc_journal_lines')) {
            return response()->json([
                'ok' => false,
                'unavailable' => true,
                'message' => 'Accounting ledger tables are not migrated on ERP.',
                'data' => ['lines' => [], 'totals' => ['debit' => 0, 'credit' => 0], 'source' => 'erp'],
            ], 200);
        }

        $q = DB::table('acc_journal_lines as jl')
            ->join('acc_journal_entries as je', 'jl.journal_entry_id', '=', 'je.id')
            ->join('acc_chart_accounts as a', 'jl.account_id', '=', 'a.id')
            ->select([
                'jl.id',
                'jl.journal_entry_id',
                'je.document_no',
                'je.document_date',
                'jl.account_id',
                'a.code as account_code',
                'a.name as account_name',
                'jl.debit',
                'jl.credit',
                'jl.description as line_description',
                'je.description as entry_description',
            ])
            ->orderByDesc('je.document_date')
            ->orderByDesc('jl.id')
            ->limit(min(500, max(1, (int) $request->input('limit', 100))));

        if ($request->filled('account_id')) {
            $q->where('jl.account_id', $request->input('account_id'));
        }
        if ($request->filled('from')) {
            $q->whereDate('je.document_date', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $q->whereDate('je.document_date', '<=', $request->input('to'));
        }

        $lines = $q->get();

        return response()->json([
            'data' => [
                'lines' => $lines,
                'totals' => [
                    'debit' => (float) $lines->sum('debit'),
                    'credit' => (float) $lines->sum('credit'),
                ],
                'source' => 'erp',
                'domain' => $domain,
            ],
        ]);
    }
}
