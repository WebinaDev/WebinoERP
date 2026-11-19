<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_companies')) {
            return;
        }
        Schema::table('crm_companies', function (Blueprint $table) {
            if (! Schema::hasColumn('crm_companies', 'english_name')) {
                $table->string('english_name', 200)->nullable()->after('legal_name');
            }
            if (! Schema::hasColumn('crm_companies', 'brand_name')) {
                $table->string('brand_name', 120)->nullable()->after('english_name');
            }
            if (! Schema::hasColumn('crm_companies', 'registration_number')) {
                $table->string('registration_number', 32)->nullable()->after('national_id');
            }
            if (! Schema::hasColumn('crm_companies', 'address')) {
                $table->text('address')->nullable()->after('currency_code');
            }
            if (! Schema::hasColumn('crm_companies', 'phone')) {
                $table->string('phone', 32)->nullable()->after('address');
            }
            if (! Schema::hasColumn('crm_companies', 'email')) {
                $table->string('email', 160)->nullable()->after('phone');
            }
            if (! Schema::hasColumn('crm_companies', 'website')) {
                $table->string('website', 200)->nullable()->after('email');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('crm_companies')) {
            return;
        }
        Schema::table('crm_companies', function (Blueprint $table) {
            foreach (['english_name', 'brand_name', 'registration_number', 'address', 'phone', 'email', 'website'] as $col) {
                if (Schema::hasColumn('crm_companies', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
