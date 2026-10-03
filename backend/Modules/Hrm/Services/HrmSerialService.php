<?php

namespace Modules\Hrm\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HrmSerialService
{
    public function next(string $scope): string
    {
        if (! Schema::hasTable('hrm_serial_sequences')) {
            return strtoupper($scope).'-'.now()->format('YmdHis');
        }

        return DB::transaction(function () use ($scope) {
            $row = DB::table('hrm_serial_sequences')->where('scope', $scope)->lockForUpdate()->first();
            if (! $row) {
                DB::table('hrm_serial_sequences')->insert([
                    'scope' => $scope,
                    'next_number' => 2,
                    'prefix' => strtoupper(substr($scope, 0, 2)).'-',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return strtoupper(substr($scope, 0, 2)).'-0001';
            }
            $n = (int) $row->next_number;
            DB::table('hrm_serial_sequences')->where('id', $row->id)->update([
                'next_number' => $n + 1,
                'updated_at' => now(),
            ]);

            return ((string) $row->prefix).str_pad((string) $n, 5, '0', STR_PAD_LEFT);
        });
    }
}
