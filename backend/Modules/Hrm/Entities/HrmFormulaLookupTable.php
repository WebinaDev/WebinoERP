<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;

class HrmFormulaLookupTable extends Model
{
    protected $table = 'hrm_formula_lookup_tables';

    protected $fillable = [
        'table_code',
        'title',
        'key_type',
        'entries',
        'description',
    ];

    protected $casts = [
        'entries' => 'array',
    ];
}
