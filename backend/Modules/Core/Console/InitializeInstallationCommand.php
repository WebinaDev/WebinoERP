<?php

namespace Modules\Core\Console;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Modules\Authz\Support\Initialization\AuthzCatalogCatalog;
use Modules\Core\Support\Initialization\RolesAndPermissionsCatalog;
use Modules\Core\Support\Initialization\SystemModulesCatalog;
use Modules\Core\Support\Initialization\SystemSettingsCatalog;
use Modules\Crm\Support\Initialization\CrmLookupCatalog;
use Modules\Marketing\Support\Initialization\MarketingFormsCatalog;
use Modules\Marketplace\Support\Initialization\SiteThemeCatalog;
use Modules\Platform\Support\Initialization\PlatformServiceTemplatesCatalog;
use Modules\Poetry\Support\Initialization\PoetryTaxonomyCatalog;
use Modules\SiteBuilder\Support\Initialization\SiteBuilderCatalog;

class InitializeInstallationCommand extends Command
{
    protected $signature = 'core:initialize-installation';

    protected $description = 'Initialize a new installation and its first administrator';

    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error(__('api.install_requires_terminal'));
            return self::FAILURE;
        }

        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('webino_installation_state')) {
            $this->error(__('api.install_not_initialized'));
            return self::FAILURE;
        }

        $state = DB::table('webino_installation_state')->where('id', 1)->value('status');
        if ($state !== 'pending' || User::query()->exists()) {
            $this->error(__('api.install_not_initialized'));
            return self::FAILURE;
        }

        $name = $this->ask(__('api.install_admin_name'));
        $email = $this->ask(__('api.install_admin_email'));
        $password = $this->secret(__('api.install_admin_password'));
        $confirmation = $this->secret(__('api.install_admin_password_confirm'));
        $validator = Validator::make(compact('name', 'email', 'password', 'confirmation'), [
            'name' => 'required|string|max:191',
            'email' => 'required|email|max:191',
            'password' => 'required|string|min:12|same:confirmation',
        ]);
        if ($validator->fails()) {
            $this->error(__('api.install_invalid_credentials'));
            return self::FAILURE;
        }

        try {
            DB::transaction(function () use ($name, $email, $password): void {
                DB::statement('LOCK TABLE users IN SHARE ROW EXCLUSIVE MODE');
                $state = DB::table('webino_installation_state')->where('id', 1)->lockForUpdate()->first();
                if (! $state || $state->status !== 'pending' || User::query()->exists()) {
                    throw new \DomainException(__('api.install_not_initialized'));
                }

                foreach ([RolesAndPermissionsCatalog::class, SystemModulesCatalog::class, SystemSettingsCatalog::class,
                    CrmLookupCatalog::class, AuthzCatalogCatalog::class, SiteBuilderCatalog::class,
                    PlatformServiceTemplatesCatalog::class, SiteThemeCatalog::class,
                    MarketingFormsCatalog::class, PoetryTaxonomyCatalog::class] as $catalog) {
                    app($catalog)->run();
                }

                if (Artisan::call('site-builder:ensure-hosting-defaults') !== 0) {
                    throw new \RuntimeException('Hosting defaults failed');
                }

                $admin = new User();
                $admin->forceFill([
                    'name' => $name,
                    'email' => $email,
                    'password' => Hash::make($password),
                    'is_active' => true,
                    'email_verified_at' => now(),
                ])->save();
                $admin->assignRole(RolesAndPermissionsCatalog::ROLE_SYSTEM_MANAGER);
                DB::table('webino_installation_state')->where('id', 1)->update(['status' => 'completed']);
            });
        } catch (\DomainException $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        } catch (\Throwable $exception) {
            Log::error('Installation initialization failed', ['exception' => get_class($exception)]);
            $this->error(__('api.install_failed'));
            return self::FAILURE;
        }

        $this->info(__('api.install_completed'));
        return self::SUCCESS;
    }
}
