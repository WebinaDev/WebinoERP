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
        $notes = collect([
            "نام: {$data['name']}",
            "ایمیل: {$data['email']}",
            $data['phone'] ? "تلفن: {$data['phone']}" : null,
            $data['company'] ? "شرکت: {$data['company']}" : null,
            $data['message'] ? "پیام: {$data['message']}" : null,
            $data['source'] ? "منبع: {$data['source']}" : 'منبع: سایت عمومی',
        ])->filter()->implode("\n");

        $consultation = CrmConsultation::query()->create([
            'title' => $title,
            'status' => 'new',
            'notes' => $notes,
        ]);

        return response()->json(['data' => ['id' => $consultation->id]], 201);
    }
}
