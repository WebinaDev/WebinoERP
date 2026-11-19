<?php

namespace Modules\Platform\Support;

/**
 * Where the tenant site app («وبینا استودیو» / Webina Studio, formerly WebinoDashboard) comes from.
 *
 * WEBINA_STUDIO_* env vars are read first; the legacy WEBINO_DASHBOARD_* names keep working
 * as a fallback so existing servers do not need any .env change.
 */
final class WebinaStudioSource
{
    public const DEFAULT_GIT_URL = 'https://github.com/Webinadev/webina-studio.git';

    public const DEFAULT_SRC = '/var/lib/webino/src/webina-studio';

    public const LEGACY_SRC = '/var/lib/webino/src/WebinoDashboard';

    /** Value of WEBINA_STUDIO_{suffix}, else WEBINO_DASHBOARD_{suffix}, else $default. */
    public static function env(string $suffix, string $default = ''): string
    {
        foreach (['WEBINA_STUDIO_', 'WEBINO_DASHBOARD_'] as $prefix) {
            $value = env($prefix.$suffix);
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return $default;
    }

    public static function gitUrl(): string
    {
        return self::env('GIT_URL', self::DEFAULT_GIT_URL);
    }

    public static function gitRef(): string
    {
        return self::env('GIT_REF', 'main');
    }

    /** Checkout dir: explicit env, else the new path, else the existing legacy checkout. */
    public static function srcPath(): string
    {
        $explicit = self::env('SRC');
        if ($explicit !== '') {
            return $explicit;
        }
        if (! is_dir(self::DEFAULT_SRC) && is_dir(self::LEGACY_SRC)) {
            return self::LEGACY_SRC;
        }

        return self::DEFAULT_SRC;
    }

    /** Build script: explicit env, else the new script, else the legacy name. */
    public static function buildScript(string $erpRoot): string
    {
        $explicit = self::env('BUILD_SCRIPT');
        if ($explicit !== '' && is_file($explicit)) {
            return $explicit;
        }
        foreach ([
            $explicit !== '' ? dirname($explicit).'/build-webina-studio-images.sh' : null,
            $erpRoot.'/scripts/build-webina-studio-images.sh',
            $explicit !== '' ? $explicit : null,
            $erpRoot.'/scripts/build-webino-dashboard-images.sh',
        ] as $candidate) {
            if (is_string($candidate) && is_file($candidate)) {
                return $candidate;
            }
        }

        return $explicit !== '' ? $explicit : $erpRoot.'/scripts/build-webina-studio-images.sh';
    }

    /**
     * Env for the build script. Both names are exported so an older script copy still works.
     *
     * @return array<string, string>
     */
    public static function scriptEnv(): array
    {
        $env = [];
        foreach (['GIT_URL' => self::gitUrl(), 'GIT_REF' => self::gitRef(), 'SRC' => self::srcPath()] as $suffix => $value) {
            $env['WEBINA_STUDIO_'.$suffix] = $value;
            $env['WEBINO_DASHBOARD_'.$suffix] = $value;
        }

        return $env;
    }
}
