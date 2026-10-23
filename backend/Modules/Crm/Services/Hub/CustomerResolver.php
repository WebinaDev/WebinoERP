<?php

namespace Modules\Crm\Services\Hub;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Services\CoreLicenseResolver;
use Modules\Crm\Entities\CrmContact;

/**
 * Best-effort mapping of foreign records (a mobile, a site domain, a portal user) to a crm_accounts row.
 * Merged accounts are followed to their survivor.
 */
class CustomerResolver
{
    public static function byMobile(?string $mobile): ?int
    {
        $n = self::normMobile($mobile);
        if ($n === null) {
            return null;
        }
        $variants = array_values(array_unique([$n, '0'.substr($n, 2), '+'.$n, substr($n, 2)]));
        $id = DB::table('crm_accounts')->whereIn('mobile', $variants)->whereNull('deleted_at')->orderBy('id')->value('id');
        if (! $id && Schema::hasTable('crm_contacts')) {
            $id = DB::table('crm_contacts')->whereIn('mobile', $variants)->whereNull('deleted_at')->orderBy('id')->value('account_id');
        }

        return self::survivor($id);
    }

    public static function byDomain(?string $domainOrUrl): ?int
    {
        $raw = (string) $domainOrUrl;
        if ($raw === '') {
            return null;
        }
        $host = str_contains($raw, '://') ? (string) parse_url($raw, PHP_URL_HOST) : $raw;
        $d = CoreLicenseResolver::normalizeDomain($host);
        if ($d === '') {
            return null;
        }
        $id = Schema::hasTable('crm_customer_domains') ? DB::table('crm_customer_domains')->whereIn('domain', [$d, 'www.'.$d])->value('account_id') : null;
        if (! $id && Schema::hasTable('webino_site_provisions')) {
            $id = DB::table('webino_site_provisions')->whereNotNull('crm_account_id')
                ->where(fn ($q) => $q->whereIn('domain', [$d, 'www.'.$d])->orWhere('subdomain', $d))->value('crm_account_id');
        }
        if (! $id) {
            $id = DB::table('crm_accounts')->whereNull('deleted_at')->where(fn ($q) => $q->where('website', 'like', '%://'.$d.'%')->orWhere('website', $d))->value('id');
        }

        return self::survivor($id);
    }

    public static function byUser(?int $userId): ?int
    {
        if (! $userId || ! Schema::hasTable('crm_account_users')) {
            return null;
        }

        return self::survivor(DB::table('crm_account_users')->where('user_id', $userId)->orderByDesc('is_primary')->value('account_id'));
    }

    /** sms related_type/related_id -> account */
    public static function byRelated(?string $type, $id): ?int
    {
        if (! $type || ! $id) {
            return null;
        }
        $t = strtolower(class_basename($type));

        return match (true) {
            in_array($t, ['account', 'crmaccount', 'crm_account', 'customer'], true) => self::survivor(DB::table('crm_accounts')->where('id', $id)->value('id')),
            in_array($t, ['contact', 'crmcontact', 'crm_contact'], true) => self::survivor(CrmContact::withTrashed()->where('id', $id)->value('account_id')),
            default => null,
        };
    }

    public static function normMobile(?string $m): ?string
    {
        $d = preg_replace('/\D+/', '', strtr((string) $m, ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9']));
        if ($d === '' || strlen($d) < 10) {
            return null;
        }
        if (str_starts_with($d, '0098')) {
            $d = substr($d, 2);
        }
        if (str_starts_with($d, '09')) {
            $d = '98'.substr($d, 1);
        } elseif (str_starts_with($d, '9') && strlen($d) === 10) {
            $d = '98'.$d;
        }

        return $d;
    }

    private static function survivor($id): ?int
    {
        for ($i = 0; $id && $i < 5; $i++) {
            $next = DB::table('crm_accounts')->where('id', $id)->value('merged_into_id');
            if (! $next) {
                return (int) $id;
            }
            $id = $next;
        }

        return $id ? (int) $id : null;
    }
}
