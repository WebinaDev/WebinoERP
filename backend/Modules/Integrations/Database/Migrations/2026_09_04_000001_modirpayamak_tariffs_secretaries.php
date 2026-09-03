<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modirpayamak_tariffs', function (Blueprint $table) {
            $table->id();
            $table->string('line_type', 50);
            $table->string('operator', 20)->default('other');
            $table->decimal('rate_fa', 14, 4)->default(0);
            $table->decimal('rate_la', 14, 4)->default(0);
            $table->integer('sort')->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['line_type', 'operator'], 'line_operator');
            $table->index('status');
            $table->index('sort');
        });

        Schema::create('modirpayamak_secretaries', function (Blueprint $table) {
            $table->id();
            $table->string('domain', 255);
            $table->string('type', 40);
            $table->string('name', 191)->default('');
            $table->text('keywords')->nullable();
            $table->longText('reply_body')->nullable();
            $table->string('pattern_code', 100)->default('');
            $table->string('forward_to', 40)->default('');
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->index('domain');
            $table->index('type');
        });

        if (Schema::hasTable('integration_settings')) {
            foreach ([
                ['sms_tax_percent', '10'],
                ['sms_surcharge_rial', '40'],
            ] as [$key, $value]) {
                $exists = DB::table('integration_settings')
                    ->where('integration', 'modirpayamak')
                    ->where('key', $key)
                    ->exists();
                if (! $exists) {
                    DB::table('integration_settings')->insert([
                        'integration' => 'modirpayamak',
                        'key' => $key,
                        'value' => $value,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        $defaults = [
            ['1000', 1936.0, 4840.0, 2129.6, 5324.0, 10],
            ['2000', 1936.0, 4840.0, 2129.6, 5324.0, 20],
            ['3000', 1936.0, 4840.0, 2129.6, 5324.0, 30],
            ['50001-50009', 1936.0, 4840.0, 2129.6, 5324.0, 40],
            ['50004', 1936.0, 4840.0, 2129.6, 5324.0, 50],
            ['BTS', 1936.0, 4840.0, 2129.6, 5324.0, 60],
            ['998', 1936.0, 4840.0, 2129.6, 5324.0, 70],
            ['voice', 2057.0, 5142.5, 2057.0, 5142.5, 80],
            ['9000', 1936.0, 4840.0, 2129.6, 5324.0, 90],
            ['EVENT', 3872.0, 9680.0, 4259.2, 10648.0, 100],
            ['bale', 2613.6, 6534.0, 2613.6, 6534.0, 110],
            ['TARGETA', 3872.0, 9680.0, 3872.0, 9680.0, 120],
        ];

        $now = now();
        $rows = [];
        foreach ($defaults as $p) {
            $rows[] = [
                'line_type' => $p[0],
                'operator' => 'mci',
                'rate_fa' => $p[1],
                'rate_la' => $p[2],
                'sort' => $p[5],
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $rows[] = [
                'line_type' => $p[0],
                'operator' => 'other',
                'rate_fa' => $p[3],
                'rate_la' => $p[4],
                'sort' => $p[5],
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('modirpayamak_tariffs')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('modirpayamak_secretaries');
        Schema::dropIfExists('modirpayamak_tariffs');
    }
};
