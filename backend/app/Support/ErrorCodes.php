<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Centralized error codes for API responses.
 *
 * Parity with webinocrm includes/class-error-codes.php.
 *
 * Usage:
 *   throw new \App\Exceptions\AppException(ErrorCodes::PRJ_ERR_CONTRACT_NOT_FOUND);
 *   return ErrorCodes::respond(ErrorCodes::PRJ_ERR_CONTRACT_NOT_FOUND, status: 404);
 */
final class ErrorCodes
{
    // ── Contract Management Errors (PRJ_0xx) ────────────────────────────────
    public const PRJ_ERR_NONCE_FAILED = 'PRJ_001';

    public const PRJ_ERR_ACCESS_DENIED = 'PRJ_002';

    public const PRJ_ERR_MISSING_CONTRACT_DATA = 'PRJ_003';

    public const PRJ_ERR_INVALID_CUSTOMER = 'PRJ_004';

    public const PRJ_ERR_INVALID_START_DATE = 'PRJ_005';

    public const PRJ_ERR_CONTRACT_SAVE_FAILED = 'PRJ_006';

    public const PRJ_ERR_INVALID_INSTALLMENT_DATE = 'PRJ_007';

    public const PRJ_ERR_CONTRACT_NOT_FOUND = 'PRJ_008';

    public const PRJ_ERR_CONTRACT_DELETE_FAILED = 'PRJ_009';

    public const PRJ_ERR_CANCEL_CONTRACT_FAILED = 'PRJ_010';

    // ── Project Management Errors (PRJ_2xx) ─────────────────────────────────
    public const PRJ_ERR_MISSING_PROJECT_DATA = 'PRJ_201';

    public const PRJ_ERR_CONTRACT_INVALID = 'PRJ_202';

    public const PRJ_ERR_PROJECT_SAVE_FAILED = 'PRJ_203';

    public const PRJ_ERR_PROJECT_NOT_FOUND = 'PRJ_204';

    public const PRJ_ERR_PROJECT_DELETE_FAILED = 'PRJ_205';

    // ── Product/Service Automation Errors (PRJ_3xx) ─────────────────────────
    public const PRJ_ERR_PRODUCT_WOC_INACTIVE = 'PRJ_301';

    public const PRJ_ERR_MISSING_PRODUCT_DATA = 'PRJ_302';

    public const PRJ_ERR_PRODUCT_NOT_FOUND = 'PRJ_303';

    public const PRJ_ERR_PRODUCT_PROJECT_FAILED = 'PRJ_304';

    // ── CRM Errors (CRM_xxx) ────────────────────────────────────────────────
    public const CRM_ERR_LEAD_NOT_FOUND = 'CRM_001';

    public const CRM_ERR_STATUS_INVALID = 'CRM_002';

    public const CRM_ERR_SOURCE_INVALID = 'CRM_003';

    public const CRM_ERR_ASSIGN_FAILED = 'CRM_004';

    public const CRM_ERR_CONVERT_FAILED = 'CRM_005';

    public const CRM_ERR_ACCOUNT_NOT_FOUND = 'CRM_010';

    public const CRM_ERR_DUPLICATE_ACCOUNT_CODE = 'CRM_011';

    public const CRM_ERR_CONSULTATION_NOT_FOUND = 'CRM_020';

    // ── Core Errors (CORE_xxx) ──────────────────────────────────────────────
    public const CORE_ERR_AUTH_INVALID = 'CORE_001';

    public const CORE_ERR_OTP_INVALID = 'CORE_002';

    public const CORE_ERR_OTP_EXPIRED = 'CORE_003';

    public const CORE_ERR_TOKEN_CONSUMED = 'CORE_004';

    public const CORE_ERR_PERMISSION_DENIED = 'CORE_005';

    public const CORE_ERR_VALIDATION = 'CORE_010';

    public const CORE_ERR_RATE_LIMIT = 'CORE_011';

    public const CORE_ERR_LICENSE_EXPIRED = 'CORE_020';

    // ── Accounting Errors (ACC_xxx) ─────────────────────────────────────────
    public const ACC_ERR_JOURNAL_UNBALANCED = 'ACC_001';

    public const ACC_ERR_FISCAL_CLOSED = 'ACC_002';

    public const ACC_ERR_INVOICE_NOT_FOUND = 'ACC_003';

    public const ACC_ERR_RECEIPT_NOT_FOUND = 'ACC_004';

    public const ACC_ERR_CHECK_STATUS_INVALID = 'ACC_005';

    public const ACC_ERR_STOCK_INSUFFICIENT = 'ACC_006';

    public const ACC_ERR_CHART_INVALID = 'ACC_007';

    // ── Integrations (INT_xxx) ──────────────────────────────────────────────
    public const INT_ERR_SMS_PROVIDER = 'INT_001';

    public const INT_ERR_SMS_SEND = 'INT_002';

    public const INT_ERR_ZARINPAL_INIT = 'INT_010';

    public const INT_ERR_ZARINPAL_VERIFY = 'INT_011';

    public const INT_ERR_BALE_SEND = 'INT_020';

    public const INT_ERR_TELEGRAM_SEND = 'INT_030';

    // ── Generic ─────────────────────────────────────────────────────────────
    public const SYS_ERR_UNKNOWN = 'SYS_999';

    public const SYS_ERR_SERVER = 'SYS_500';

    public const SYS_ERR_NOT_FOUND = 'SYS_404';

    /**
     * @var array<string,string>
     */
    private static array $messages = [];

    public static function message(string $code): string
    {
        return __('errors.'.strtolower($code));
    }

    /**
     * Build a structured JSON error response and log the event.
     *
     * @param  array<string,mixed>  $context
     */
    public static function respond(string $code, array $context = [], int $status = 400, ?string $message = null): JsonResponse
    {
        $text = $message ?? self::message($code);

        Log::warning('error.'.$code, array_merge($context, [
            'error_code' => $code,
            'message' => $text,
        ]));

        return response()->json([
            'success' => false,
            'data' => null,
            'message' => $text,
            'meta' => null,
            'errors' => [
                'code' => $code,
                'context' => $context,
            ],
        ], $status);
    }

    /**
     * @return array<string,string>
     */
    public static function all(): array
    {
        return self::$messages;
    }
}
