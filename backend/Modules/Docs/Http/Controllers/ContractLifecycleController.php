<?php

namespace Modules\Docs\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Docs\Entities\DocsContract;
use Modules\Docs\Entities\DocsSignatureEnvelope;
use Modules\Docs\Services\ContractLifecycleService;

class ContractLifecycleController extends Controller
{
    public function transition(Request $request, DocsContract $contract, ContractLifecycleService $service): JsonResponse
    {
        $data = $request->validate([
            'status' => 'required|string',
            'reason' => 'nullable|string',
        ]);

        try {
            $updated = $service->transitionState($contract, $data['status'], $data['reason'] ?? null);

            return response()->json([
                'data' => $updated,
                'message' => "وضعیت قرارداد با موفقیت به '{$data['status']}' منتقل شد.",
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function createEnvelope(Request $request, DocsContract $contract, ContractLifecycleService $service): JsonResponse
    {
        $data = $request->validate([
            'signer_name' => 'required|string|max:150',
            'signer_mobile' => 'required|string|max:20',
        ]);

        $envelope = $service->createSignatureEnvelope($contract, $data['signer_name'], $data['signer_mobile']);

        return response()->json([
            'data' => $envelope,
            'message' => "پاکت امضای الکترونیک برای {$data['signer_name']} با موفقیت ایجاد و ارسال گردید.",
        ], 201);
    }

    public function sign(Request $request, DocsSignatureEnvelope $envelope, ContractLifecycleService $service): JsonResponse
    {
        $data = $request->validate([
            'otp' => 'required|string|size:6',
        ]);

        try {
            $service->signEnvelope($envelope, $data['otp'], $request->ip() ?: '127.0.0.1', $request->userAgent() ?: 'Agent');

            return response()->json([
                'message' => 'قرارداد با موفقیت به صورت الکترونیک امضا و سند قفل گردید.',
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function addAddendum(Request $request, DocsContract $contract, ContractLifecycleService $service): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|string|in:amount_change,extension,scope_change,mutual_termination',
            'amount_delta' => 'nullable|numeric',
            'new_end_date' => 'nullable|date',
            'terms_and_body' => 'required|string',
        ]);

        $addendum = $service->createAddendum($contract, $data);

        return response()->json([
            'data' => $addendum,
            'message' => "الحاقیه رسمی شماره {$addendum->addendum_number} با موفقیت ثبت و روی قرارداد اعمال شد.",
        ], 201);
    }
}
