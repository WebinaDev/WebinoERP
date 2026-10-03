<?php

namespace Modules\Marketing\Http\Controllers\Public;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Crm\Entities\CrmConsultation;
use Modules\Marketing\Http\Controllers\Controller;

class PublicConsultationController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:32',
            'subject' => 'nullable|string|max:255',
            'message' => 'nullable|string',
            'company' => 'nullable|string|max:255',
            'source' => 'nullable|string|max:64',
        ]);

        $title = $data['subject'] ?? 'درخواست مشاوره از سایت';
        $phone = $data['phone'] ?? null;
        $company = $data['company'] ?? null;
        $message = $data['message'] ?? null;
        $source = $data['source'] ?? null;
        $notes = collect([
            "نام: {$data['name']}",
            "ایمیل: {$data['email']}",
            $phone ? "تلفن: {$phone}" : null,
            $company ? "شرکت: {$company}" : null,
            $message ? "پیام: {$message}" : null,
            $source ? "منبع: {$source}" : 'منبع: سایت عمومی',
        ])->filter()->implode("\n");

        $consultation = CrmConsultation::query()->create([
            'title' => $title,
            'status' => 'new',
            'notes' => $notes,
        ]);

        return response()->json(['data' => ['id' => $consultation->id]], 201);
    }
}
