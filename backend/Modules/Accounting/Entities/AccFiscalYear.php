<?php

namespace Modules\Accounting\Entities;

use Illuminate\Database\Eloquent\Model;

class AccFiscalYear extends Model
{
    protected $table = 'acc_fiscal_years';

    protected $fillable = ['title', 'starts_on', 'ends_on', 'is_closed'];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_closed' => 'boolean',
        ];
    }
}
