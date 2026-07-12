<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * WordPress / webinocrm MySQL → Webino Eloquent importer (parity scaffold).
 */
class WebinoImportWordpressCommand extends Command
{
    protected $signature = 'webino:import:wordpress {--host=} {--db=} {--prefix=wp_}';

    protected $description = 'Import legacy webinocrm data from a WordPress MySQL database (configure connection flags)';

    public function handle(): int
    {
        $this->warn('Importer scaffold: configure --host/--db and map CPTs in a follow-up migration run.');
        $this->line('Planned mappings: users, leads, projects, contracts, tasks, tickets, appointments, invoices, attachments.');

        return self::SUCCESS;
    }
}
