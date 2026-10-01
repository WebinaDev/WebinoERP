<?php

namespace Modules\SiteBuilder\Support;

use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Launch writes webino_site_provisions.progress. If that column was never
 * migrated, the update throws and the wizard used to hide the SQL behind a
 * generic queue/validation error while the row stayed draft.
 */
class LaunchSchema
{
    public const OUTDATED = 'platform.schema_outdated';

    public const QUEUE_UNAVAILABLE = 'platform.queue_unavailable';

    public const PROGRESS_MIGRATION = '2026_09_04_163000_add_progress_to_webino_site_provisions';

    public static function missingProgressColumn(): bool
    {
        try {
            return ! Schema::hasTable('webino_site_provisions')
                || ! Schema::hasColumn('webino_site_provisions', 'progress');
        } catch (Throwable) {
            return true;
        }
    }

    public static function isProgressFailure(Throwable $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'progress')
            && (
                str_contains($message, 'webino_site_provisions')
                || str_contains($message, 'column')
                || str_contains($message, 'sqlstate')
            );
    }

    /**
     * @return array{message: string, errors: array{code: string, progress: list<string>}}
     */
    public static function outdatedPayload(?string $detail = null): array
    {
        $detail = trim((string) $detail);
        if ($detail === '') {
            $detail = 'webino_site_provisions.progress is missing. Run php artisan migrate --force ('
                .self::PROGRESS_MIGRATION.').';
        }

        return [
            'message' => self::OUTDATED,
            'errors' => [
                'code' => self::OUTDATED,
                'progress' => [$detail],
            ],
        ];
    }

    /**
     * @return array{message: string, errors: array{code: string}}
     */
    public static function queueUnavailablePayload(): array
    {
        return [
            'message' => self::QUEUE_UNAVAILABLE,
            'errors' => [
                'code' => self::QUEUE_UNAVAILABLE,
            ],
        ];
    }
}
