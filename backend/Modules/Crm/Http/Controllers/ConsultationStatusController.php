<?php

namespace Modules\Crm\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Crm\Entities\ConsultationStatus;

class ConsultationStatusController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => ConsultationStatus::query()->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }
}
