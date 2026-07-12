<?php

namespace Modules\Core\Services;

use App\Models\User;
use Modules\Core\Entities\FieldPermission;

/**
 * Parity with webinocrm class-field-security.php: evaluate per-role view/edit access and apply masking.
 */
class FieldSecurityService
{
    /**
     * Returns `true` when the user's role(s) satisfy the configured view rule for the field.
     *
     * Defaults to `true` when no rule exists — parity with WordPress default (`allow view`).
     */
    public function canView(User $user, string $entity, string $field): bool
    {
        if ($user->hasRole('system_manager')) {
            return true;
        }
        $rule = $this->rule($entity, $field);
        if ($rule === null || empty($rule['view_roles'])) {
            return true;
        }

        return $this->hasAnyRole($user, $rule['view_roles']);
    }

    /**
     * Parity: default is deny for edit when a rule is missing in webinocrm.
     */
    public function canEdit(User $user, string $entity, string $field): bool
    {
        if ($user->hasRole('system_manager')) {
            return true;
        }
        $rule = $this->rule($entity, $field);
        if ($rule === null) {
            return true;
        }
        if (empty($rule['edit_roles'])) {
            return false;
        }

        return $this->hasAnyRole($user, $rule['edit_roles']);
    }

    /**
     * Apply view-time masking / removal rules to a plain associative payload.
     *
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    public function applyToArray(?User $user, string $entity, array $data): array
    {
        $matrix = FieldPermission::matrix()[$entity] ?? FieldPermission::defaults()[$entity] ?? [];
        if (empty($matrix) || $user === null) {
            return $data;
        }
        foreach ($matrix as $field => $rule) {
            if (! array_key_exists($field, $data)) {
                continue;
            }
            if (! $this->canView($user, $entity, $field)) {
                unset($data[$field]);

                continue;
            }
            if (! empty($rule['mask_view']) && $rule['mask_strategy']) {
                $data[$field] = $this->mask($rule['mask_strategy'], $data[$field]);
            }
        }

        return $data;
    }

    /**
     * @param  list<string>  $roles
     */
    private function hasAnyRole(User $user, array $roles): bool
    {
        return $user->roles()->whereIn('name', $roles)->exists();
    }

    /**
     * @return array{view_roles: list<string>, edit_roles: list<string>, mask_view: bool, mask_strategy: ?string}|null
     */
    private function rule(string $entity, string $field): ?array
    {
        $configured = FieldPermission::matrix()[$entity][$field] ?? null;
        if ($configured) {
            return $configured;
        }

        return FieldPermission::defaults()[$entity][$field] ?? null;
    }

    public function mask(string $strategy, mixed $value): mixed
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return $value;
        }
        $s = (string) $value;

        return match ($strategy) {
            'email' => $this->maskEmail($s),
            'phone' => $this->maskPhone($s),
            'bank' => $this->maskTail($s, 4),
            'card' => $this->maskTail($s, 4),
            default => $s,
        };
    }

    private function maskEmail(string $email): string
    {
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email;
        }
        [$u, $d] = explode('@', $email, 2);
        $keep = mb_substr($u, 0, 2);

        return $keep.str_repeat('*', max(0, mb_strlen($u) - 2)).'@'.$d;
    }

    private function maskPhone(string $phone): string
    {
        $digits = preg_replace('/[^0-9]/', '', $phone) ?? '';
        if (strlen($digits) < 6) {
            return '****';
        }

        return substr($digits, 0, 3).str_repeat('*', strlen($digits) - 6).substr($digits, -3);
    }

    private function maskTail(string $v, int $tail): string
    {
        $len = strlen($v);
        if ($len <= $tail) {
            return str_repeat('*', $len);
        }

        return str_repeat('*', $len - $tail).substr($v, -$tail);
    }
}
