<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_rahn_settings', function (Blueprint $table) {
            $table->id();
            $table->json('payload');
            $table->timestamps();
        });

        Schema::create('sales_rahn_quotes', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->string('title')->default('');
            $table->string('status', 20)->default('draft');
            $table->unsignedBigInteger('customer_id')->default(0)->index();
            $table->unsignedBigInteger('lead_id')->default(0);
            $table->unsignedBigInteger('contract_id')->default(0)->index();
            $table->json('selected_ids')->nullable();
            $table->json('items_snapshot')->nullable();
            $table->json('calc_snapshot')->nullable();
            $table->double('s_hat')->default(0);
            $table->unsignedInteger('duration')->default(6);
            $table->string('mode', 20)->default('from_p');
            $table->double('p_wanted')->default(0);
            $table->double('f_wanted')->default(0);
            $table->double('F')->default(0);
            $table->double('p')->default(0);
            $table->timestamp('locked_at')->nullable();
            $table->text('clause')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->index('status');
        });

        Schema::create('sales_rahn_statements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contract_id')->index();
            $table->unsignedBigInteger('quote_id')->default(0);
            $table->string('year_month', 7);
            $table->double('G')->default(0);
            $table->double('R')->default(0);
            $table->double('D')->default(0);
            $table->double('X')->default(0);
            $table->double('S')->default(0);
            $table->double('F')->default(0);
            $table->double('p')->default(0);
            $table->double('V')->default(0);
            $table->double('C')->nullable();
            $table->double('Pi')->nullable();
            $table->unsignedBigInteger('invoice_id')->default(0);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->unique(['contract_id', 'year_month'], 'sales_rahn_contract_month');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_rahn_statements');
        Schema::dropIfExists('sales_rahn_quotes');
        Schema::dropIfExists('sales_rahn_settings');
    }
};
