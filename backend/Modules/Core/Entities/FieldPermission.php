<?php

namespace Modules\Core\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Parity with webinocrm `webinocrm_field_permissions` option (class-field-security.php).
 *
 * Each row defines per-(entity, field) role lists for view/edit and optional mask strategy.
 */
class FieldPermission extends Model
{
    public const CACHE_KEY = 'core:field_permissions';

    protected $table = 'core_field_permissions';

    protected $fillable = [
        'entity_type',
        'field_name',
        'view_roles',
        'edit_roles',
        'mask_view',
        'mask_strategy',
        'updated_by',
    ];

    protected $casts = [
        'view_roles' => 'array',
        'edit_roles' => 'array',
        'mask_view' => 'boolean',
    ];

    protected static function booted(): void
    {
        $flush = static fn () => Cache::forget(self::CACHE_KEY);
        static::saved($flush);
        static::deleted($flush);
    }

    /**
     * Returns a nested map [entity_type => [field_name => [view_roles, edit_roles, mask_view, mask_strategy]]].
     */
    public static function matrix(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function (): array {
            $out = [];
            foreach (self::query()->get() as $row) {
                $out[$row->entity_type][$row->field_name] = [
                    'view_roles' => $row->view_roles ?: [],
                    'edit_roles' => $row->edit_roles ?: [],
                    'mask_view' => (bool) $row->mask_view,
                    'mask_strategy' => $row->mask_strategy,
                ];
            }

            return $out;
        });
    }

    /**
     * Built-in defaults derived from webinocrm `setup_default_permissions()`.
     *
     * @return array<string, array<string, array{view_roles: list<string>, edit_roles: list<string>, mask_view: bool, mask_strategy: ?string}>>
     */
    public static function defaults(): array
    {
        $admin = ['system_manager'];
        $managerPlus = ['system_manager', 'finance_manager'];
        $teamLead = ['system_manager', 'finance_manager', 'sales_consultant'];
        $everyone = ['system_manager', 'finance_manager', 'sales_consultant', 'team_member'];

        return [
            'lead' => [
                'value' => ['view_roles' => $teamLead, 'edit_roles' => $managerPlus, 'mask_view' => false, 'mask_strategy' => null],
                'source' => ['view_roles' => $everyone, 'edit_roles' => $teamLead, 'mask_view' => false, 'mask_strategy' => null],
                'phone' => ['view_roles' => $everyone, 'edit_roles' => $everyone, 'mask_view' => false, 'mask_strategy' => 'phone'],
                'email' => ['view_roles' => $everyone, 'edit_roles' => $teamLead, 'mask_view' => false, 'mask_strategy' => 'email'],
            ],
            'project' => [
                'budget' => ['view_roles' => $teamLead, 'edit_roles' => $managerPlus, 'mask_view' => false, 'mask_strategy' => null],
                'cost' => ['view_roles' => $managerPlus, 'edit_roles' => $managerPlus, 'mask_view' => false, 'mask_strategy' => null],
                'profit_margin' => ['view_roles' => $managerPlus, 'edit_roles' => $admin, 'mask_view' => false, 'mask_strategy' => null],
            ],
            'contract' => [
                'value' => ['view_roles' => $teamLead, 'edit_roles' => $managerPlus, 'mask_view' => false, 'mask_strategy' => null],
                'commission' => ['view_roles' => $managerPlus, 'edit_roles' => $admin, 'mask_view' => false, 'mask_strategy' => null],
                'payment_terms' => ['view_roles' => $teamLead, 'edit_roles' => $managerPlus, 'mask_view' => false, 'mask_strategy' => null],
            ],
            'task' => [
                'estimated_hours' => ['view_roles' => $everyone, 'edit_roles' => $teamLead, 'mask_view' => false, 'mask_strategy' => null],
                'actual_hours' => ['view_roles' => $teamLead, 'edit_roles' => $everyone, 'mask_view' => false, 'mask_strategy' => null],
            ],
            'user' => [
                'salary' => ['view_roles' => $admin, 'edit_roles' => $admin, 'mask_view' => false, 'mask_strategy' => null],
                'commission_rate' => ['view_roles' => $managerPlus, 'edit_roles' => $admin, 'mask_view' => false, 'mask_strategy' => null],
                'bank_account' => ['view_roles' => $admin, 'edit_roles' => $admin, 'mask_view' => true, 'mask_strategy' => 'bank'],
            ],
        ];
    }
}
