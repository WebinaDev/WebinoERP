<?php

namespace Modules\Core\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class CoreLicense extends Model
{
    protected $table = 'core_licenses';

    protected $fillable = [
        'license_key', 'project_name', 'domain', 'logo_url', 'status', 'start_date',
        'expires_at', 'max_users', 'meta', 'created_by',
    ];

    /** @var list<string>|null */
    protected static ?array $presentColumns = null;

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'start_date' => 'date',
            'meta' => 'array',
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public static function attributesForSchema(array $attributes): array
    {
        $columns = self::presentColumns();
        $out = [];
        foreach ($attributes as $key => $value) {
            if (in_array($key, $columns, true)) {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function createForSchema(array $attributes): self
    {
        return static::query()->create(self::attributesForSchema($attributes));
    }

    /** @return list<string> */
    public static function presentColumns(): array
    {
        if (self::$presentColumns === null) {
            self::$presentColumns = Schema::getColumnListing((new static)->getTable());
        }

        return self::$presentColumns;
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
