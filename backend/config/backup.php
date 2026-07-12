<?php

return [
    'backup' => [
        'name' => env('APP_NAME', 'webino-erm'),
        'source' => [
            'files' => [
                'include' => [
                    base_path(),
                ],
                'exclude' => [
                    base_path('vendor'),
                    base_path('node_modules'),
                    storage_path('app/backups'),
                ],
                'follow_links' => false,
                'ignore_unreadable_directories' => true,
                'relative_path' => null,
            ],
            'databases' => [
                env('DB_CONNECTION', 'pgsql'),
            ],
        ],
        'destination' => [
            'disks' => [
                'local',
            ],
        ],
        'temporary_directory' => storage_path('app/backup-temp'),
        'password' => env('BACKUP_ARCHIVE_PASSWORD'),
        'encryption' => env('BACKUP_ENCRYPTION', 'default'),
    ],

    'monitor_backups' => [
        [
            'name' => env('APP_NAME', 'webino-erm'),
            'disks' => ['local'],
            'health_checks' => [
                \Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays::class => 1,
                \Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes::class => 5000,
            ],
        ],
    ],

    'cleanup' => [
        'strategy' => \Spatie\Backup\Tasks\Cleanup\Strategies\DefaultStrategy::class,
        'default_strategy' => [
            'keep_all_backups_for_days' => 7,
            'keep_daily_backups_for_days' => 16,
            'keep_weekly_backups_for_weeks' => 8,
            'keep_monthly_backups_for_months' => 4,
            'keep_yearly_backups_for_years' => 2,
            'delete_oldest_backups_when_using_more_megabytes_than' => 5000,
        ],
    ],
];
