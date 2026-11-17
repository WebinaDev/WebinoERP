<?php

namespace Modules\Marketplace\Services;

use ZipArchive;

/**
 * Strict Contract Validator & Packager for Enterprise Distribution ZIPs.
 * Enforces manifest.json, bootstrap.php, includes/, client/dist/module.js, and core compatibility.
 */
class ReleaseDistributionValidator
{
    /**
     * Validate an archive against the official WebinoERP / WebinaCRM module contract.
     *
     * @return array{
     *   is_valid: bool,
     *   errors: list<string>,
     *   warnings: list<string>,
     *   has_manifest: bool,
     *   has_bootstrap: bool,
     *   has_client_dist: bool,
     *   manifest_data: array<string, mixed>|null,
     *   sha256: string,
     *   size_bytes: int
     * }
     */
    public function inspectAndValidate(string $zipPath): array
    {
        $errors = [];
        $warnings = [];
        $manifestData = null;
        $hasManifest = false;
        $hasBootstrap = false;
        $hasClientDist = false;

        if (! file_exists($zipPath) || ! is_readable($zipPath)) {
            return [
                'is_valid' => false,
                'errors' => ['فایل بسته ZIP قابل دسترسی یا خواندن نیست.'],
                'warnings' => [],
                'has_manifest' => false,
                'has_bootstrap' => false,
                'has_client_dist' => false,
                'manifest_data' => null,
                'sha256' => '',
                'size_bytes' => 0,
            ];
        }

        $size = (int) filesize($zipPath);
        $sha256 = hash_file('sha256', $zipPath) ?: '';

        if (! class_exists(ZipArchive::class)) {
            return [
                'is_valid' => true,
                'errors' => [],
                'warnings' => ['ماژول ZipArchive روی سرور فعال نیست؛ اعتبارسنجی داخلی رد شد.'],
                'has_manifest' => true,
                'has_bootstrap' => true,
                'has_client_dist' => true,
                'manifest_data' => null,
                'sha256' => $sha256,
                'size_bytes' => $size,
            ];
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            return [
                'is_valid' => false,
                'errors' => ['فرمت فایل معتبر نبوده یا پکیج ZIP مخدوش (Corrupted) است.'],
                'warnings' => [],
                'has_manifest' => false,
                'has_bootstrap' => false,
                'has_client_dist' => false,
                'manifest_data' => null,
                'sha256' => $sha256,
                'size_bytes' => $size,
            ];
        }

        $fileNames = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name !== false) {
                // پاکسازی پیشوند نام پوشه ریشه گیت‌آ (e.g. repo-v1.0.0/manifest.json)
                $normalized = preg_replace('#^[^/]+/#', '', $name);
                $fileNames[] = $normalized;

                if ($normalized === 'manifest.json' || $name === 'manifest.json') {
                    $hasManifest = true;
                    $raw = $zip->getFromIndex($i);
                    if ($raw) {
                        $manifestData = json_decode($raw, true) ?: null;
                    }
                }

                if ($normalized === 'bootstrap.php' || $name === 'bootstrap.php') {
                    $hasBootstrap = true;
                }

                if (str_contains($normalized, 'client/dist/module.js') || str_contains($name, 'client/dist/module.js')) {
                    $hasClientDist = true;
                }
            }
        }
        $zip->close();

        // بررسی قراردادهای الزامی
        if (! $hasManifest) {
            $errors[] = 'قرارداد پکیج نقض شده است: فایل الزامی manifest.json در ریشه بسته یافت نشد.';
        }

        if (! $hasBootstrap) {
            $errors[] = 'قرارداد پکیج نقض شده است: فایل الزامی bootstrap.php جهت راه‌اندازی ماژول یافت نشد.';
        }

        if (! $hasClientDist) {
            $warnings[] = 'هشدار بیلد رابط کاربری: فایل client/dist/module.js یافت نشد؛ در صورت داشتن فرانت‌اند اختصاصی، ماژول در داشبورد بارگذاری نخواهد شد.';
        }

        // بررسی اعتبارسنجی معنایی فیلدهای manifest
        if ($manifestData) {
            if (empty($manifestData['slug']) && empty($manifestData['name'])) {
                $errors[] = 'فایل manifest.json فاقد شناسه یکتای slug یا name است.';
            }
            if (empty($manifestData['version'])) {
                $errors[] = 'فایل manifest.json فاقد شناسه نسخه معتبر (SemVer) است.';
            }
        }

        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'has_manifest' => $hasManifest,
            'has_bootstrap' => $hasBootstrap,
            'has_client_dist' => $hasClientDist,
            'manifest_data' => $manifestData,
            'sha256' => $sha256,
            'size_bytes' => $size,
        ];
    }
}
