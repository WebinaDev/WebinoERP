<?php

namespace Modules\Sales\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Sales\Services\RahnService;

class RahnPublicController extends Controller
{
    public function __construct(private readonly RahnService $rahn) {}

    public function show(string $token): JsonResponse
    {
        return response()->json(['data' => $this->rahn->publicGet($token)]);
    }

    public function calculate(Request $request, string $token): JsonResponse
    {
        return response()->json(['data' => $this->rahn->publicCalculate($token, $request->all())]);
    }

    public function submit(Request $request, string $token): JsonResponse
    {
        $result = $this->rahn->publicSubmit($token, $request->all());

        return response()->json([
            'data' => $result,
            'message' => $result['message'] ?? null,
        ]);
    }

    /** Token-free website calculator (/pricing). */
    public function siteConfig(): JsonResponse
    {
        return response()->json(['data' => $this->rahn->siteConfig()]);
    }

    public function siteCalculate(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->rahn->siteCalculate($request->all())]);
    }

    public function siteSubmit(Request $request): JsonResponse
    {
        // Honeypot / too-fast submissions are acknowledged but dropped.
        if (filled($request->input('_hp')) || ($request->has('_t') && (int) $request->input('_t') < 1500)) {
            return response()->json(['data' => ['message' => 'درخواست شما ثبت شد. به‌زودی با شما تماس می‌گیریم.'], 'message' => null]);
        }
        $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:190'],
            'note' => ['nullable', 'string', 'max:3000'],
            'business_name' => ['nullable', 'string', 'max:190'],
        ]);
        $result = $this->rahn->siteSubmit($request->all());

        return response()->json(['data' => $result, 'message' => $result['message'] ?? null]);
    }
}
