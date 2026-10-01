<?php

namespace Modules\SiteBuilder\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
use Modules\SiteBuilder\Support\LaunchSchema;
use Throwable;

class AssertSchemaCommand extends Command
{
    protected $signature = 'site-builder:assert-schema';

    protected $description = 'Fail if migrations are still pending or webino_site_provisions.progress is missing';

    public function handle(Migrator $migrator): int
    {
        if (! $migrator->repositoryExists()) {
            $this->error('ERROR: migration repository is missing. Run: php artisan migrate --force');

            return self::FAILURE;
        }

        try {
            // MigrateCommand adds database/migrations beside the paths modules register.
            $paths = array_values(array_unique(array_merge(
                $migrator->paths(),
                [database_path('migrations')],
            )));
            $files = $migrator->getMigrationFiles($paths);
            $ran = $migrator->getRepository()->getRan();
        } catch (Throwable $e) {
            $this->error('ERROR: could not read migration status: '.$e->getMessage());

            return self::FAILURE;
        }

        $pending = array_values(array_diff(array_keys($files), $ran));
        if ($pending !== []) {
            $this->error('ERROR: '.count($pending).' migration(s) still pending after migrate --force:');
            foreach ($pending as $name) {
                $this->error('  Pending  '.$name);
            }
            $this->error('Site launch stays draft until these apply. Re-run: php artisan migrate --force');

            return self::FAILURE;
        }

        if (LaunchSchema::missingProgressColumn()) {
            $this->error('ERROR: column webino_site_provisions.progress is missing.');
            $this->error('Expected migration '.LaunchSchema::PROGRESS_MIGRATION.' to have run.');
            $this->error('«ایجاد سایت» updates that column and will fail until you run: php artisan migrate --force');

            return self::FAILURE;
        }

        $this->info('Schema OK: no pending migrations, webino_site_provisions.progress exists.');

        return self::SUCCESS;
    }
}
