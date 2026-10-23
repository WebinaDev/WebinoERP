<?php

namespace Modules\Crm\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Crm\Console\BackfillCustomerActivitiesCommand;
use Modules\Crm\Console\DispatchContentRemindersCommand;
use Modules\Crm\Console\DispatchRemindersCommand;
use Modules\Crm\Console\PublishDueContentCommand;
use Modules\Crm\Console\RecomputeLeadScoresCommand;
use Modules\Crm\Console\RetryMoadianCommand;
use Modules\Crm\Console\RunSequencesCommand;
use Modules\Crm\Console\SyncCustomerSitesCommand;
use Modules\Crm\Events\Customer\ContractSigned;
use Modules\Crm\Events\CustomerHubUpdated;
use Modules\Crm\Http\Controllers\ConsultationIngestController;
use Modules\Crm\Http\Controllers\CustomerHubController;
use Modules\Crm\Http\Controllers\ElementorLeadController;
use Modules\Crm\Http\Controllers\PublicLeadFormController;
use Modules\Crm\Listeners\RecordCustomerActivity;
use Modules\Crm\Observers\CustomerSyncObserver;

class CrmServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Crm';

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                RecomputeLeadScoresCommand::class,
                DispatchRemindersCommand::class,
                RunSequencesCommand::class,
                DispatchContentRemindersCommand::class,
                PublishDueContentCommand::class,
                RetryMoadianCommand::class,
                SyncCustomerSitesCommand::class,
                BackfillCustomerActivitiesCommand::class,
            ]);
        }

        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));

        // Customer 360 unified timeline: cross-module sync events -> crm_customer_activities.
        $l = RecordCustomerActivity::class;
        foreach ([
            'DealWon', 'DealLost', 'InvoiceIssued', 'InvoicePaid', 'InvoiceOverdue', 'PaymentRecorded', 'ContractCancelled',
            'ProjectCreated', 'ProjectStatusChanged', 'TicketOpened', 'TicketClosed', 'OrderPlaced', 'SiteProvisioned', 'CustomerMerged',
        ] as $ev) {
            Event::listen('Modules\\Crm\\Events\\Customer\\'.$ev, $l);
        }
        Event::listen(ContractSigned::class, [$l, 'contractSigned']);
        Event::listen(CustomerHubUpdated::class, [$l, 'hubUpdated']);
        CustomerSyncObserver::register();

        Route::prefix('api/v1/crm')
            ->middleware(['api', 'throttle:60,1'])
            ->group(function () {
                Route::post('/leads/elementor', [ElementorLeadController::class, 'store']);
                Route::post('/public/forms/{slug}', [PublicLeadFormController::class, 'store'])
                    ->where('slug', '[a-z0-9\-]+');
            });

        Route::prefix('api/webinocrm/v1')
            ->middleware(['api', 'throttle:60,1'])
            ->group(function () {
                Route::post('/consultations/ingest', [ConsultationIngestController::class, 'store']);
            });

        // Customer 360 Hub: section-level RBAC is enforced in CustomerHubAccess so finance /
        // support roles without crm.accounts.view can still open their own sections.
        // Signed (temporary) download of private hub documents — visibility checked when the link is issued.
        Route::prefix('api/v1/crm/customers')->middleware(['api', 'signed', 'throttle:60,1'])
            ->get('/{id}/hub/documents/{docId}/file', [CustomerHubController::class, 'downloadDocument'])
            ->whereNumber(['id', 'docId'])->name('crm.hub.document.file');

        Route::prefix('api/v1/crm/customers')
            ->middleware(['api', 'auth:sanctum', 'module:crm'])
            ->group(module_path($this->moduleName, 'Routes/hub.php'));

        Route::prefix('api/v1/crm')
            ->middleware(['api', 'auth:sanctum', 'module:crm', 'module.permission:crm'])
            ->group(module_path($this->moduleName, 'Routes/api.php'));
    }

    public function register(): void
    {
        //
    }
}
