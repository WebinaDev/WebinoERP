<?php

namespace Modules\Sales\Http\Controllers\Storefront;

use Illuminate\Http\JsonResponse;
use Modules\Sales\Http\Controllers\Controller;

/** Iranian provinces for storefront address forms. */
class GeoController extends Controller
{
    private const STATES = [
        'THR' => 'تهران', 'ALB' => 'البرز', 'ESF' => 'اصفهان', 'FAR' => 'فارس', 'KHR' => 'خراسان رضوی', 'AZS' => 'آذربایجان شرقی',
        'AZG' => 'آذربایجان غربی', 'ARD' => 'اردبیل', 'BSH' => 'بوشهر', 'CHB' => 'چهارمحال و بختیاری', 'KHJ' => 'خراسان جنوبی',
        'KHS' => 'خراسان شمالی', 'KHZ' => 'خوزستان', 'ZJN' => 'زنجان', 'SMN' => 'سمنان', 'SBN' => 'سیستان و بلوچستان', 'QZV' => 'قزوین',
        'QHM' => 'قم', 'KRD' => 'کردستان', 'KRN' => 'کرمان', 'KRH' => 'کرمانشاه', 'KBD' => 'کهگیلویه و بویراحمد', 'GLS' => 'گلستان',
        'GIL' => 'گیلان', 'LRS' => 'لرستان', 'MZN' => 'مازندران', 'MKZ' => 'مرکزی', 'HRZ' => 'هرمزگان', 'HMD' => 'همدان', 'YZD' => 'یزد', 'ILM' => 'ایلام',
    ];

    public function states(): JsonResponse
    {
        $rows = [];
        foreach (self::STATES as $code => $name) {
            $rows[] = ['code' => $code, 'name' => $name];
        }

        return response()->json(['data' => $rows])->header('Cache-Control', 'public, max-age=86400');
    }

    public static function valid(?string $code): bool
    {
        return $code !== null && array_key_exists($code, self::STATES);
    }
}
