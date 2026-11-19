<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Display-name rename only: «Webino Dashboard» is now «وبینا استودیو» (Webina Studio).
 * Touches a row only while it still carries the exact seeded name, so edited content is kept.
 * Keys (slug webino-dashboard, resource type webino_dashboard, license product webinodashboard)
 * are intentionally unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->rename('platform_service_templates', 'name', 'Webino Dashboard', 'وبینا استودیو (Webina Studio)', ['slug' => 'webino-dashboard']);
        $this->rename('marketing_portfolio_items', 'title', 'Webino Dashboard', 'وبینا استودیو', ['slug' => 'webino-dashboard']);
    }

    public function down(): void
    {
        $this->rename('platform_service_templates', 'name', 'وبینا استودیو (Webina Studio)', 'Webino Dashboard', ['slug' => 'webino-dashboard']);
        $this->rename('marketing_portfolio_items', 'title', 'وبینا استودیو', 'Webino Dashboard', ['slug' => 'webino-dashboard']);
    }

    /** @param  array<string, string>  $where */
    private function rename(string $table, string $column, string $from, string $to, array $where): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }
        DB::table($table)->where($where)->where($column, $from)->update([$column => $to]);
    }
};
