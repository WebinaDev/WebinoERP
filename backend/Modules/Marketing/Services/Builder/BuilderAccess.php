<?php

namespace Modules\Marketing\Services\Builder;

use Modules\Marketing\Services\Builder\BuilderSite;

use Modules\Marketing\Entities\BuilderRolePolicy;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Per-role builder permissions on top of the content capability.
 * edit = change drafts, publish = publish/unpublish/schedule, design = globals, theme templates, kits.
 * Admins are always allowed; a role without a policy row keeps full access (backwards compatible).
 */
class BuilderAccess
{
    public const ABILITIES = ['edit', 'publish', 'design'];

    public static function allows(?User $user, string $ability): bool
    {
        if (! $user) {
            return false;
        }
        $role = self::roleOf($user);
        if ($role === 'admin') {
            return true;
        }
        $row = BuilderRolePolicy::query()->where('tenant_id', BuilderSite::ID)->where('role', $role)->first();
        if (! $row) {
            return true;
        }

        return (bool) $row->{'can_'.$ability};
    }

    /** ERP uses spatie roles: system managers / super admins count as admin; otherwise the first role name. */
    public static function roleOf(User $user): string
    {
        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['super-admin', 'super_admin', 'system_manager', 'admin'])) {
            return 'admin';
        }
        $legacy = (string) ($user->getAttribute('role') ?? '');
        if ($legacy !== '') {
            return $legacy;
        }

        return method_exists($user, 'getRoleNames') ? (string) ($user->getRoleNames()->first() ?? '') : '';
    }

    public static function authorize(Request $request, string $ability): void
    {
        abort_unless(self::allows($request->user(), $ability), 403, 'builder_'.$ability.'_forbidden');
    }

    /** @return array<string, bool> */
    public static function abilities(?User $user): array
    {
        $out = [];
        foreach (self::ABILITIES as $ability) {
            $out[$ability] = self::allows($user, $ability);
        }

        return $out;
    }
}
