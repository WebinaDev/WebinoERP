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
}
