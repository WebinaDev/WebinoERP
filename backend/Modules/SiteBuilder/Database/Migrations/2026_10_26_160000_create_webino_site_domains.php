<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Platform\Support\TenantAliasDomains;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('webino_site_domains')) {
            Schema::create('webino_site_domains', function (Blueprint $table) {
                $table->id();
                $table->foreignId('provision_id')->constrained('webino_site_provisions')->cascadeOnDelete();
                $table->string('domain', 253)->unique();
                $table->string('role', 16)->default('alias'); // primary | alias | redirect
                $table->boolean('include_www')->default(true);
                $table->string('redirect_path', 255)->nullable();
                $table->unsignedSmallInteger('redirect_status')->default(301);
                $table->boolean('preserve_path')->default(true);
                $table->timestamps();
                $table->index(['provision_id', 'role']);
            });
        }

        // Backfill: each provision's primary domain + legacy domains (as alias). First owner wins.
        $taken = DB::table('webino_site_domains')->pluck('domain')->flip()->all();
        $rows = DB::table('webino_site_provisions')
            ->whereNotNull('domain')->where('domain', '!=', '')
            ->orderByRaw("CASE WHEN status = 'ready' THEN 0 ELSE 1 END")->orderBy('id')
            ->get(['id', 'domain', 'wizard_payload']);
        $now = now();
        foreach ($rows as $row) {
            $primary = TenantAliasDomains::normalizeHost((string) $row->domain);
            if ($primary === '' || ! str_contains($primary, '.') || isset($taken[$primary])) {
                continue;
            }
            DB::table('webino_site_domains')->insert([
                'provision_id' => $row->id, 'domain' => $primary, 'role' => 'primary',
                // false keeps existing snippets byte-identical; enable www per site from the UI
                'include_www' => false, 'redirect_status' => 301, 'preserve_path' => true,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $taken[$primary] = true;
            $wizard = is_string($row->wizard_payload) ? json_decode($row->wizard_payload, true) : (array) $row->wizard_payload;
            foreach ((array) ($wizard['legacy_domains'] ?? []) as $legacy) {
                $h = TenantAliasDomains::normalizeHost(is_string($legacy) ? $legacy : '');
                if ($h === '' || ! str_contains($h, '.') || isset($taken[$h])) {
                    continue;
                }
                DB::table('webino_site_domains')->insert([
                    'provision_id' => $row->id, 'domain' => $h, 'role' => 'alias',
                    'include_www' => false, 'redirect_status' => 301, 'preserve_path' => true,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
                $taken[$h] = true;
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('webino_site_domains');
    }
};
