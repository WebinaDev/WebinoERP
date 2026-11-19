<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\StaffNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Database\Seeders\RolesAndPermissionsSeeder;
use Modules\Core\Entities\CoreNotification;
use Modules\Core\Entities\CoreUserDocument;
use Modules\Core\Services\NotificationPreferences;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

class UserManagementApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    private function admin(): User
    {
        $admin = $this->actingAsRole(RolesAndPermissionsSeeder::ROLE_SYSTEM_MANAGER);
        Sanctum::actingAs($admin);

        return $admin;
    }

    public function test_non_admin_cannot_manage_users(): void
    {
        $member = $this->actingAsRole(RolesAndPermissionsSeeder::ROLE_TEAM_MEMBER);
        $other = User::factory()->create();
        Sanctum::actingAs($member);

        $this->getJson('/api/v1/core/users/directory')->assertForbidden();
        $this->getJson("/api/v1/core/users/{$other->id}")->assertForbidden();
        $this->postJson("/api/v1/core/users/{$other->id}/password", ['mode' => 'generate'])->assertForbidden();
        $this->getJson("/api/v1/core/users/{$other->id}/documents")->assertForbidden();
    }

    public function test_directory_filters_staff_and_customers(): void
    {
        $this->admin();
        $staff = $this->actingAsRole(RolesAndPermissionsSeeder::ROLE_TEAM_MEMBER);
        $client = $this->actingAsRole(RolesAndPermissionsSeeder::ROLE_CLIENT);

        $res = $this->getJson('/api/v1/core/users/directory?type=customer')->assertOk();
        $ids = collect($res->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($client->id));
        $this->assertFalse($ids->contains($staff->id));
        $this->assertSame('customer', collect($res->json('data'))->firstWhere('id', $client->id)['type']);

        $res = $this->getJson('/api/v1/core/users/directory?type=staff')->assertOk();
        $this->assertTrue(collect($res->json('data'))->pluck('id')->contains($staff->id));
        $this->assertGreaterThanOrEqual(3, $res->json('meta.stats.total'));
    }

    public function test_admin_creates_user_with_username_and_temp_password_forces_change(): void
    {
        $this->admin();
        $res = $this->postJson('/api/v1/core/users/accounts', [
            'name' => 'کاربر آزمایشی',
            'email' => 'Test.User@example.com',
            'username' => 'Test.User',
            'roles' => [RolesAndPermissionsSeeder::ROLE_CLIENT],
        ])->assertCreated();
        $temp = $res->json('data.temporary_password');
        $this->assertNotEmpty($temp);
        $user = User::query()->where('email', 'test.user@example.com')->firstOrFail();
        $this->assertSame('test.user', $user->username);
        $this->assertTrue($user->must_change_password);
        $this->assertTrue($user->hasRole(RolesAndPermissionsSeeder::ROLE_CLIENT));

        // Login by username works.
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/core/auth/login', ['email' => 'test.user', 'password' => $temp])
            ->assertOk()
            ->assertJsonPath('data.user.must_change_password', true);
    }

    public function test_must_change_password_blocks_writes_until_changed(): void
    {
        $user = User::factory()->create(['password' => Hash::make('Temp-pass-123'), 'is_active' => true]);
        $user->assignRole(RolesAndPermissionsSeeder::ROLE_TEAM_MEMBER);
        $user->forceFill(['must_change_password' => true])->save();
        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/core/me', ['name' => 'X'])->assertForbidden()
            ->assertJsonPath('errors.code', 'PASSWORD_CHANGE_REQUIRED');
        $this->getJson('/api/v1/core/me')->assertOk();

        $this->postJson('/api/v1/core/auth/password/set', [
            'current_password' => 'Temp-pass-123',
            'password' => 'New-pass-4567',
            'password_confirmation' => 'New-pass-4567',
        ])->assertOk();
        $this->assertFalse($user->fresh()->must_change_password);
        $this->assertNotNull($user->fresh()->password_changed_at);
        $this->patchJson('/api/v1/core/me', ['name' => 'X'])->assertOk();
    }

    public function test_admin_sets_password_and_revokes_sessions(): void
    {
        $this->admin();
        $user = User::factory()->create(['is_active' => true]);
        $user->createToken('spa');
        $user->createToken('spa');

        $this->postJson("/api/v1/core/users/{$user->id}/password", [
            'mode' => 'set',
            'password' => 'Brand-new-999',
            'password_confirmation' => 'Brand-new-999',
            'must_change_password' => true,
        ])->assertOk()->assertJsonPath('data.revoked_sessions', 2);

        $fresh = $user->fresh();
        $this->assertTrue(Hash::check('Brand-new-999', $fresh->password));
        $this->assertTrue($fresh->must_change_password);
        $this->assertSame(0, $fresh->tokens()->count());
        $this->getJson("/api/v1/core/users/{$user->id}/activity")->assertOk()
            ->assertJsonPath('data.0.action', 'user.password_reset');
    }

    public function test_lock_blocks_login_and_unlock_restores(): void
    {
        $this->admin();
        $user = User::factory()->create(['email' => 'lock@example.com', 'password' => Hash::make('secret-123'), 'is_active' => true]);

        $this->postJson("/api/v1/core/users/{$user->id}/lock", ['locked' => true, 'reason' => 'بررسی امنیتی'])
            ->assertOk()->assertJsonPath('data.locked', true);
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/core/auth/login', ['email' => 'lock@example.com', 'password' => 'secret-123'])
            ->assertForbidden();

        $this->admin();
        $this->postJson("/api/v1/core/users/{$user->id}/lock", ['locked' => false])->assertOk()->assertJsonPath('data.locked', false);
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/core/auth/login', ['email' => 'lock@example.com', 'password' => 'secret-123'])->assertOk();
    }

    public function test_account_profile_and_roles_update(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['is_active' => true]);

        $this->patchJson("/api/v1/core/users/{$user->id}/account", ['username' => 'ali_r', 'phone' => '09120000000', 'is_active' => false])
            ->assertOk()->assertJsonPath('data.username', 'ali_r')->assertJsonPath('data.is_active', false);
        $this->putJson("/api/v1/core/users/{$user->id}/profile", ['national_id' => '0012345678', 'city' => 'تهران', 'birth_date' => '1990-01-02'])
            ->assertOk()->assertJsonPath('data.city', 'تهران');
        $this->putJson("/api/v1/core/users/{$user->id}/roles", ['roles' => [RolesAndPermissionsSeeder::ROLE_HR_MANAGER]])
            ->assertOk()->assertJsonPath('data.roles.0', RolesAndPermissionsSeeder::ROLE_HR_MANAGER);

        // Self-protection.
        $this->patchJson("/api/v1/core/users/{$admin->id}/account", ['is_active' => false])->assertStatus(422);
        $this->putJson("/api/v1/core/users/{$admin->id}/roles", ['roles' => []])->assertStatus(422);
    }

    public function test_non_system_manager_admin_cannot_touch_system_manager_or_grant_it(): void
    {
        $sm = $this->actingAsRole(RolesAndPermissionsSeeder::ROLE_SYSTEM_MANAGER);
        $hr = $this->actingAsRole(RolesAndPermissionsSeeder::ROLE_HR_MANAGER);
        $hr->givePermissionTo(['core.users.view', 'core.users.manage']);
        $target = User::factory()->create();
        Sanctum::actingAs($hr);

        $this->postJson("/api/v1/core/users/{$sm->id}/password", ['mode' => 'generate'])->assertForbidden();
        $this->putJson("/api/v1/core/users/{$target->id}/roles", ['roles' => [RolesAndPermissionsSeeder::ROLE_SYSTEM_MANAGER]])->assertForbidden();
        $this->putJson("/api/v1/core/users/{$target->id}/roles", ['roles' => [RolesAndPermissionsSeeder::ROLE_TEAM_MEMBER]])->assertOk();
    }

    public function test_documents_are_private_and_downloadable_by_admin_and_owner(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $this->admin();
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(RolesAndPermissionsSeeder::ROLE_TEAM_MEMBER);

        $res = $this->post("/api/v1/core/users/{$user->id}/documents", [
            'file' => UploadedFile::fake()->create('card.pdf', 120, 'application/pdf'),
            'type' => 'national_card',
            'title' => 'کارت ملی',
            'expires_at' => now()->addDays(10)->toDateString(),
        ], ['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])->assertCreated();
        $docId = $res->json('data.id');
        $this->assertTrue($res->json('data.expires_soon'));
        $this->assertArrayNotHasKey('path', $res->json('data'));
        $doc = CoreUserDocument::query()->findOrFail($docId);
        Storage::disk('local')->assertExists($doc->path);
        $this->assertSame([], Storage::disk('public')->allFiles());

        $this->get("/api/v1/core/users/{$user->id}/documents/{$docId}/download", ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();

        // Replace file bumps the version and removes the old file.
        $oldPath = $doc->path;
        $this->post("/api/v1/core/users/{$user->id}/documents/{$docId}", [
            'file' => UploadedFile::fake()->create('card2.pdf', 50, 'application/pdf'),
            'notes' => 'نسخه جدید',
        ], ['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])->assertOk()->assertJsonPath('data.version', 2);
        Storage::disk('local')->assertMissing($oldPath);

        // Owner sees and downloads it; another member cannot reach it.
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/core/me/documents')->assertOk()->assertJsonPath('data.0.id', $docId);
        $this->get("/api/v1/core/me/documents/{$docId}/download", ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();
        $other = $this->actingAsRole(RolesAndPermissionsSeeder::ROLE_TEAM_MEMBER);
        Sanctum::actingAs($other);
        $this->get("/api/v1/core/me/documents/{$docId}/download", ['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])->assertNotFound();

        $this->admin();
        $this->deleteJson("/api/v1/core/users/{$user->id}/documents/{$docId}")->assertOk();
        $this->assertDatabaseMissing('core_user_documents', ['id' => $docId]);
    }

    public function test_notification_preferences_are_respected_by_senders(): void
    {
        Mail::fake();
        $this->admin();
        $user = User::factory()->create(['is_active' => true, 'email' => 'n@example.com']);

        $this->putJson("/api/v1/core/users/{$user->id}/notification-preferences", [
            'channels' => ['email' => false],
            'categories' => ['hrm' => ['enabled' => false], 'security' => ['enabled' => false]],
            'quiet_hours' => ['start' => '23:00', 'end' => '07:00'],
        ])->assertOk()
            ->assertJsonPath('data.channels.email', false)
            ->assertJsonPath('data.categories.hrm.enabled', false)
            ->assertJsonPath('data.categories.security.enabled', true)
            ->assertJsonPath('data.quiet_hours.start', '23:00');

        $prefs = new NotificationPreferences;
        $this->assertFalse($prefs->allows($user->id, 'email', 'general'));
        $this->assertFalse($prefs->allows($user->id, 'in_app', 'hrm.leave_approved'));
        $this->assertTrue($prefs->allows($user->id, 'in_app', 'crm.lead.converted'));
        $this->assertTrue($prefs->allows($user->id, 'sms', 'security.otp'));

        // In-app notice for an off category is not stored; others are.
        CoreNotification::query()->create(['user_id' => $user->id, 'type' => 'hrm.leave_approved', 'data' => [], 'is_read' => false]);
        CoreNotification::query()->create(['user_id' => $user->id, 'type' => 'crm.lead.converted', 'data' => [], 'is_read' => false]);
        $this->assertSame(['crm.lead.converted'], CoreNotification::query()->where('user_id', $user->id)->pluck('type')->all());

        // StaffNotifier skips email when the email channel is off.
        StaffNotifier::notify($user->id, 'task.assigned', 'وظیفه', 'متن');
        Mail::assertNothingOutgoing();
    }

    public function test_self_service_profile_and_preferences(): void
    {
        $user = $this->actingAsRole(RolesAndPermissionsSeeder::ROLE_CLIENT);
        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/core/me', ['name' => 'مشتری', 'city' => 'شیراز', 'username' => 'customer1'])
            ->assertOk()->assertJsonPath('data.profile.city', 'شیراز')->assertJsonPath('data.username', 'customer1');
        $this->putJson('/api/v1/core/me/notification-preferences', ['channels' => ['sms' => false]])
            ->assertOk()->assertJsonPath('data.channels.sms', false);
        $this->getJson('/api/v1/core/me/notification-preferences')->assertOk()->assertJsonPath('data.channels.sms', false);
        // Legacy self endpoints are no longer behind the user admin permission.
        $this->getJson('/api/v1/core/users/me/preferences')->assertOk();
    }
}
