<?php

namespace Modules\Integrations\Entities;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Services\CoreDataEncryption;

class IntegrationSetting extends Model
{
    protected $table = 'integration_settings';

    protected $fillable = ['integration', 'key', 'value'];

    public static function getJson(string $integration, string $key, mixed $default = []): mixed
    {
        $row = static::query()->where('integration', $integration)->where('key', $key)->first();
        if (! $row || $row->value === null || $row->value === '') {
            return $default;
        }
        $decoded = json_decode($row->value, true);
        if (! is_array($decoded)) {
            return $default;
        }

        $enc = app(CoreDataEncryption::class);

        return $enc->decryptSettings($decoded);
    }

    public static function putJson(string $integration, string $key, array $value): void
    {
        $enc = app(CoreDataEncryption::class);
        $payload = $enc->encryptSettings($value);
        static::query()->updateOrCreate(
            ['integration' => $integration, 'key' => $key],
            ['value' => json_encode($payload, JSON_UNESCAPED_UNICODE)]
        );
    }

    public static function getString(string $integration, string $key, string $default = ''): string
    {
        $row = static::query()->where('integration', $integration)->where('key', $key)->first();
        if (! $row || $row->value === null) {
            return $default;
        }
        $val = (string) $row->value;
        $enc = app(CoreDataEncryption::class);
        if ($enc->isSensitiveKey($key)) {
            $decoded = $enc->decrypt($val);

            return $decoded ?? $default;
        }

        return $val;
    }

    public static function putString(string $integration, string $key, string $value): void
    {
        $enc = app(CoreDataEncryption::class);
        $store = $enc->isSensitiveKey($key) ? ($enc->encrypt($value) ?? $value) : $value;
        static::query()->updateOrCreate(
            ['integration' => $integration, 'key' => $key],
            ['value' => $store]
        );
    }
}
