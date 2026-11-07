<?php

namespace Modules\Integrations\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Integrations\Entities\PaymentIntent;

/**
 * Auto-submit HTML form for PSPs that require POST to the bank page.
 */
class PaymentFormBridgeController extends Controller
{
    public function show(Request $request, string $publicId): Response
    {
        $intent = PaymentIntent::query()->where('public_id', $publicId)->first();
        if (! $intent) {
            return response('Intent not found', 404);
        }
        $payload = is_array($intent->provider_payload) ? $intent->provider_payload : [];
        $action = (string) ($payload['form_action'] ?? '');
        $fields = $payload['form_fields'] ?? [];
        if ($action === '' || ! is_array($fields) || $fields === []) {
            return response('Bridge form incomplete', 422);
        }
        $method = strtoupper((string) ($payload['form_method'] ?? 'POST'));
        if (! in_array($method, ['POST', 'GET'], true)) {
            $method = 'POST';
        }
        $inputs = '';
        foreach ($fields as $k => $v) {
            $inputs .= '<input type="hidden" name="'.e((string) $k).'" value="'.e((string) $v).'">';
        }
        $html = '<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><title>در حال انتقال به درگاه…</title></head>'
            .'<body><p>در حال انتقال به درگاه پرداخت…</p>'
            .'<form id="gw" method="'.$method.'" action="'.e($action).'">'.$inputs.'</form>'
            .'<script>document.getElementById("gw").submit();</script></body></html>';

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
