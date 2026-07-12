<?php

namespace Modules\Core\Services;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Parity with webinocrm class-data-encryption.php.
 *
 * Laravel already provides robust AES-256-GCM encryption (via `Crypt`) keyed on `APP_KEY`.
 * This service wraps those primitives and adds:
 *   - sensitive-field detection (integration credentials, bank / card / SSN / phone / email)
 *   - mask helpers for display
 *   - password hash helpers parity with the WP version (bcrypt + cost=12)
 *   - sensitive Integration credential auto-encrypt/decrypt glue
 *
 * For credentials stored via `IntegrationSetting` the service offers `encryptSettings()` /
 * `decryptSettings()` which walk a nested array and encrypt values whose keys match the
 * sensitive-key list.
 */
class CoreDataEncryption
{
    /**
     * Parity with `webinocrm_sensitive_fields` filter defaults.
     *
     * @var list<string>
     */
    public const SENSITIVE_KEYS = [
        'password',
        'api_key',
        'api_secret',
        'secret',
        'secret_key',
        'token',
        'access_token',
        'refresh_token',
        'bot_token',
        'webhook_secret',
        'merchant_id',
        'merchant',
        'bank_account',
        'iban',
        'card_number',
        'credit_card',
        'national_id',
        'ssn',
        'tax_id',
    ];

    private const ENCRYPTED_MARKER = 'enc:v1:';

    public function encrypt(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }
        if (str_starts_with($value, self::ENCRYPTED_MARKER)) {
            return $value;
        }

        return self::ENCRYPTED_MARKER.Crypt::encryptString($value);
    }

    public function decrypt(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }
        if (! str_starts_with($value, self::ENCRYPTED_MARKER)) {
            return $value;
        }
        try {
            return Crypt::decryptString(substr($value, strlen(self::ENCRYPTED_MARKER)));
        } catch (DecryptException) {
            return null;
        }
    }

    public function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower(trim($key, '_'));
        foreach (self::SENSITIVE_KEYS as $needle) {
            if ($normalized === $needle || str_contains($normalized, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Encrypt sensitive leaves in a nested settings array (values only).
     *
     * @param  array<string,mixed>  $settings
     * @return array<string,mixed>
     */
    public function encryptSettings(array $settings): array
    {
        foreach ($settings as $k => $v) {
            if (is_array($v)) {
                $settings[$k] = $this->encryptSettings($v);

                continue;
            }
            if (is_string($v) && $this->isSensitiveKey((string) $k)) {
                $settings[$k] = $this->encrypt($v);
            }
        }

        return $settings;
    }

    /**
     * @param  array<string,mixed>  $settings
     * @return array<string,mixed>
     */
    public function decryptSettings(array $settings): array
    {
        foreach ($settings as $k => $v) {
            if (is_array($v)) {
                $settings[$k] = $this->decryptSettings($v);

                continue;
            }
            if (is_string($v) && str_starts_with($v, self::ENCRYPTED_MARKER)) {
                $settings[$k] = $this->decrypt($v);
            }
        }

        return $settings;
    }

    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public static function generateToken(int $length = 32): string
    {
        $length = max(8, $length);

        return bin2hex(random_bytes((int) ceil($length / 2)));
    }

    public static function maskCard(string $card): string
    {
        $digits = preg_replace('/\D+/', '', $card) ?? '';
        $len = strlen($digits);
        if ($len < 13) {
            return '****';
        }

        return str_repeat('*', $len - 4).substr($digits, -4);
    }
}
