<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\Console\Tester\CommandTester;

if (getenv('WEBINO_INSTALL_TEST') !== '1' || getenv('DB_DATABASE') !== 'webino_install_test_3' || getenv('DB_HOST') !== 'webino-install-validation-db-1123') {
    throw new RuntimeException('This test requires the dedicated isolated installation database.');
}

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
$command = $kernel->all()['core:initialize-installation'];

$check = static function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};
$run = static function (array $inputs) use ($command): int {
    $tester = new CommandTester($command);
    $tester->setInputs($inputs);
    return $tester->execute([], ['interactive' => true]);
};

$check(DB::table('webino_installation_state')->where('id', 1)->value('status') === 'pending', 'Installation is not pending.');
$check(DB::table('users')->count() === 0, 'The test database already contains users.');
$check(DB::table('roles')->count() === 0, 'Migrations created installation roles.');
$check(DB::table('webino_business_types')->count() === 0, 'Migrations created installation site types.');
$accountingResponse = $app->make(\Modules\Accounting\Http\Controllers\ChartAccountController::class)
    ->seedChartIran(\Illuminate\Http\Request::create('/chart/seed', 'POST'));
$check($accountingResponse->getStatusCode() === 403, 'Accounting seeding is available in production.');
$response = $app->make(\Modules\Core\Http\Controllers\AuthParityController::class)
    ->register(\Illuminate\Http\Request::create('/api/v1/core/auth/register', 'POST'));
$check($response->getStatusCode() === 503, 'Registration is available while installation is pending.');
$check($run(['user_001', 'test@example.local', 'short', 'short']) !== 0, 'Invalid password was accepted.');
$check(DB::table('users')->count() === 0, 'Invalid input created a user.');
$moduleCatalog = \Modules\Core\Support\Initialization\SystemModulesCatalog::class;
$app->instance($moduleCatalog, new class extends \Modules\Core\Support\Initialization\SystemModulesCatalog {
    public function run(): void { throw new RuntimeException('Synthetic initialization failure'); }
});
$check($run(['user_001', 'test@example.local', 'fake-test-password-001', 'fake-test-password-001']) !== 0, 'Initialization failure was ignored.');
$check(DB::table('users')->count() === 0 && DB::table('webino_installation_state')->where('id', 1)->value('status') === 'pending', 'Failed initialization was not rolled back.');
$app->forgetInstance($moduleCatalog);
$check($run(['user_001', 'test@example.local', 'fake-test-password-001', 'fake-test-password-001']) === 0, 'Initialization failed.');
$user = DB::table('users')->first();
$check($user !== null && $user->email === 'test@example.local' && Hash::check('fake-test-password-001', $user->password), 'Administrator was not created correctly.');
$check(DB::table('model_has_roles')->count() === 1, 'Administrator role was not assigned.');
$check(DB::table('webino_business_types')->count() >= 1, 'Site types are missing.');
$check(DB::table('authz_policies')->count() >= 1, 'Authorization policies are missing.');
$check(DB::table('platform_servers')->where('name', 'localhost')->exists(), 'Hosting defaults are missing.');
$check(DB::table('webino_installation_state')->where('id', 1)->value('status') === 'completed', 'Installation was not marked completed.');
$http = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$request = \Illuminate\Http\Request::create('/api/v1/core/auth/login', 'POST', [
    'email' => 'test@example.local',
    'password' => 'fake-test-password-001',
], server: ['HTTP_ACCEPT' => 'application/json']);
$login = $http->handle($request);
$check($login->getStatusCode() === 200, 'The first administrator could not log in through the HTTP application.');
$check(count($login->headers->getCookies()) > 0, 'The login response did not create an authentication cookie.');
$http->terminate($request, $login);
DB::table('system_settings')->where('key', 'brand_name')->update(['value' => 'test_custom_brand']);
$check($run([]) !== 0, 'Repeated initialization was accepted.');
$check(DB::table('users')->count() === 1 && DB::table('users')->value('password') === $user->password, 'Repeated initialization changed the user.');
$check(DB::table('system_settings')->where('key', 'brand_name')->value('value') === 'test_custom_brand', 'Repeated initialization changed settings.');
fwrite(STDOUT, "Installation integration checks passed.\n");
