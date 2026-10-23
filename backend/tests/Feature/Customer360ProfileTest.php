<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Database\Seeders\RolesAndPermissionsSeeder as R;
use Modules\Crm\Entities\CrmAccount;
use Modules\Crm\Entities\CrmContact;
use Modules\Crm\Support\IranianIds;
use Modules\Sales\Entities\SalesInvoice;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

class Customer360ProfileTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    private CrmAccount $acc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        $this->seedLicensedModules();
        $this->acc = CrmAccount::query()->create(['name' => 'Profile Co', 'type' => 'customer']);
    }

    private function url(string $p = ''): string
    {
        return "/api/v1/crm/customers/{$this->acc->id}/hub/".$p;
    }

    public function test_iranian_id_validators(): void
    {
        $this->assertTrue(IranianIds::isNationalCode('0013542419'));
        $this->assertTrue(IranianIds::isNationalCode('۰۰۱۳۵۴۲۴۱۹'));
        $this->assertTrue(IranianIds::isNationalCode('001-354241-9'));
        $this->assertFalse(IranianIds::isNationalCode('1234567890'));
        $this->assertFalse(IranianIds::isNationalCode('1111111111'));
        $this->assertFalse(IranianIds::isNationalCode('123'));
        $this->assertTrue(IranianIds::isLegalId('10380284790'));
        $this->assertTrue(IranianIds::isLegalId('14007650912'));
        $this->assertFalse(IranianIds::isLegalId('10380284791'));
        $this->assertFalse(IranianIds::isLegalId('0013542419'));
        $this->assertTrue(IranianIds::isIban('IR062960000000100324200001'));
        $this->assertTrue(IranianIds::isIban('062960000000100324200001'));
        $this->assertFalse(IranianIds::isIban('IR062960000000100324200002'));
        $this->assertSame('IR06••••••••••••••••••0001', IranianIds::maskIban('IR062960000000100324200001'));
    }

    public function test_full_profile_update_with_conditional_id_validation(): void
    {
        Sanctum::actingAs($this->actingAsRole(R::ROLE_SYSTEM_MANAGER));
        $this->patchJson($this->url('profile'), ['customer_kind' => 'legal', 'national_id' => '0013542419'])
            ->assertStatus(422)->assertJsonValidationErrors('national_id');
        $this->patchJson($this->url('profile'), ['customer_kind' => 'individual', 'national_id' => '10380284790'])
            ->assertStatus(422)->assertJsonValidationErrors('national_id');
        $this->patchJson($this->url('profile'), ['mobile' => '12345', 'postal_code' => '12', 'email' => 'x', 'parent_id' => $this->acc->id])
            ->assertStatus(422)->assertJsonValidationErrors(['mobile', 'postal_code', 'email', 'parent_id']);

        $res = $this->patchJson($this->url('profile'), [
            'customer_kind' => 'legal', 'national_id' => '۱۰۳۸۰۲۸۴۷۹۰', 'economic_code' => '411111111111',
            'phone' => '02188776655', 'mobile' => '09121234567', 'email' => 'Info@Example.IR',
            'socials' => ['instagram' => 'profileco'], 'tags' => ['vip', 'b2b'], 'source' => 'referral', 'segment' => 'smb',
            'custom_fields' => ['contract_tier' => 'gold'], 'currency' => 'IRR', 'timezone' => 'Asia/Tehran',
            'preferred_channel' => 'sms', 'postal_code' => '1234567890',
            'credit_limit' => 5000000, 'payment_terms_days' => 30, 'default_discount_pct' => 7.5,
            'iban' => 'IR06 2960 0000 0010 0324 2000 01', 'kyc_status' => 'verified', 'allowed_ips' => ['1.2.3.4'],
        ])->assertOk();
        $res->assertJsonPath('data.national_id', '10380284790')
            ->assertJsonPath('data.email', 'info@example.ir')
            ->assertJsonPath('data.tags', ['vip', 'b2b'])
            ->assertJsonPath('data.iban_masked', 'IR06••••••••••••••••••0001')
            ->assertJsonPath('data.payment_terms_days', 30)
            ->assertJsonPath('data.can_edit_sensitive', true);
        $this->assertArrayNotHasKey('iban', $res->json('data'));

        $fresh = $this->acc->fresh();
        $this->assertSame('IR062960000000100324200001', $fresh->iban);
        $this->assertNotNull($fresh->kyc_verified_at);
        $raw = DB::table('crm_accounts')->where('id', $this->acc->id)->value('iban');
        $this->assertStringNotContainsString('0324200001', (string) $raw);

        $audit = DB::table('crm_customer_audit')->where('account_id', $this->acc->id)->where('action', 'profile.sensitive_update')->first();
        $this->assertNotNull($audit);
        $this->assertStringNotContainsString('0324200001', $audit->changes);

        $this->getJson($this->url('profile'))->assertOk()->assertJsonPath('data.source', 'referral')->assertJsonPath('data.credit_limit', fn ($v) => (float) $v === 5000000.0);
        // the generic section route still works next to the new ones
        $this->getJson($this->url('identity'))->assertOk();
    }

    public function test_sensitive_fields_need_separate_permission(): void
    {
        Sanctum::actingAs($this->actingAsRole(R::ROLE_CRM_SPECIALIST));
        $this->patchJson($this->url('profile'), ['segment' => 'enterprise', 'tags' => ['x']])->assertOk()
            ->assertJsonPath('data.can_edit_sensitive', false)
            ->assertJsonMissingPath('data.credit_limit');
        foreach ([['credit_limit' => 1], ['iban' => 'IR062960000000100324200001'], ['kyc_status' => 'verified'], ['allowed_ips' => ['1.1.1.1']]] as $body) {
            $this->patchJson($this->url('profile'), $body)->assertForbidden();
        }
        $this->assertNull($this->acc->fresh()->credit_limit);
        $this->postJson($this->url('merge'), ['source_id' => $this->acc->id])->assertForbidden();

        Sanctum::actingAs($this->actingAsRole(R::ROLE_FINANCE_MANAGER));
        $this->patchJson($this->url('profile'), ['segment' => 'nope'])->assertForbidden();
    }

    public function test_addresses_crud_keeps_single_default_per_type(): void
    {
        Sanctum::actingAs($this->actingAsRole(R::ROLE_SYSTEM_MANAGER));
        $a1 = $this->postJson($this->url('addresses'), ['type' => 'billing', 'line' => 'Tehran, Valiasr', 'postal_code' => '۱۲۳۴۵۶۷۸۹۰'])
            ->assertCreated()->assertJsonPath('data.is_default', true)->assertJsonPath('data.postal_code', '1234567890')->json('data.id');
        $a2 = $this->postJson($this->url('addresses'), ['type' => 'billing', 'line' => 'Karaj', 'is_default' => true])->assertCreated()->json('data.id');
        $this->postJson($this->url('addresses'), ['type' => 'shipping', 'line' => 'Shiraz'])->assertCreated()->assertJsonPath('data.is_default', true);
        $this->postJson($this->url('addresses'), ['type' => 'moon', 'line' => 'x'])->assertStatus(422);
        $this->assertFalse((bool) DB::table('crm_account_addresses')->find($a1)->is_default);

        $this->patchJson($this->url("addresses/$a1"), ['is_default' => true, 'city' => 'Tehran'])->assertOk()->assertJsonPath('data.city', 'Tehran');
        $this->assertFalse((bool) DB::table('crm_account_addresses')->find($a2)->is_default);
        $this->deleteJson($this->url("addresses/$a1"))->assertNoContent();
        $this->assertTrue((bool) DB::table('crm_account_addresses')->find($a2)->is_default);
        $this->getJson($this->url('addresses'))->assertOk()->assertJsonCount(2, 'data');
        $this->getJson($this->url('profile'))->assertJsonCount(2, 'data.addresses');

        $other = CrmAccount::query()->create(['name' => 'Other', 'type' => 'customer']);
        $this->patchJson("/api/v1/crm/customers/{$other->id}/hub/addresses/$a2", ['city' => 'x'])->assertNotFound();
    }

    public function test_logo_upload(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->actingAsRole(R::ROLE_SYSTEM_MANAGER));
        $url = $this->post($this->url('logo'), ['logo' => UploadedFile::fake()->image('logo.png', 64, 64)], ['Accept' => 'application/json'])
            ->assertOk()->json('data.logo_url');
        $this->assertStringContainsString('crm/customer-logos/'.$this->acc->id, $url);
        $this->assertSame($url, $this->acc->fresh()->logo_url);
        $this->post($this->url('logo'), ['logo' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])->assertStatus(422);
    }

    public function test_merge_moves_everything_and_marks_source(): void
    {
        Sanctum::actingAs($this->actingAsRole(R::ROLE_SYSTEM_MANAGER));
        $src = CrmAccount::query()->create(['name' => 'Dup Co', 'type' => 'customer', 'mobile' => '09120000000', 'tags' => ['old'], 'iban' => 'IR062960000000100324200001']);
        $this->acc->update(['tags' => ['vip']]);
        CrmContact::query()->create(['account_id' => $src->id, 'first_name' => 'Ali', 'last_name' => 'R']);
        SalesInvoice::query()->create(['number' => 'M-1', 'customer_name' => 'Dup', 'customer_id' => $src->id, 'total' => 10, 'status' => 'paid']);
        DB::table('core_licenses')->insert(['license_key' => 'K-M', 'domain' => 'dup.ir', 'product' => 'webino', 'status' => 'active', 'crm_account_id' => $src->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('crm_account_addresses')->insert(['account_id' => $src->id, 'type' => 'billing', 'line' => 'x', 'is_default' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('crm_customer_relations')->insert(['account_id' => $src->id, 'related_account_id' => $this->acc->id, 'relation_type' => 'partner', 'created_at' => now(), 'updated_at' => now()]);
        $child = CrmAccount::query()->create(['name' => 'Child', 'type' => 'customer', 'parent_id' => $src->id]);

        $this->postJson($this->url('merge'), ['source_id' => $this->acc->id])->assertStatus(422);
        $res = $this->postJson($this->url('merge'), ['source_id' => $src->id])->assertOk();
        $this->assertSame(1, $res->json('data.moved')['sales_invoices.customer_id']);

        $this->assertSame(1, CrmContact::query()->where('account_id', $this->acc->id)->count());
        $this->assertSame($this->acc->id, (int) DB::table('sales_invoices')->where('number', 'M-1')->value('customer_id'));
        $this->assertSame($this->acc->id, (int) DB::table('core_licenses')->where('license_key', 'K-M')->value('crm_account_id'));
        $this->assertSame(1, DB::table('crm_account_addresses')->where('account_id', $this->acc->id)->count());
        $this->assertSame(0, DB::table('crm_customer_relations')->count());
        $this->assertSame($this->acc->id, (int) $child->fresh()->parent_id);
        $fresh = $this->acc->fresh();
        $this->assertSame('09120000000', $fresh->mobile);
        $this->assertSame(['vip', 'old'], $fresh->tags);
        $this->assertSame('IR062960000000100324200001', $fresh->iban);

        $gone = CrmAccount::withTrashed()->find($src->id);
        $this->assertTrue($gone->trashed());
        $this->assertSame($this->acc->id, (int) $gone->merged_into_id);
        $this->assertTrue(DB::table('crm_customer_audit')->where('account_id', $this->acc->id)->where('action', 'customer.merge')->exists());
    }
}
