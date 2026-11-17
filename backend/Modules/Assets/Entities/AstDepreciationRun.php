<?php

namespace Modules\Assets\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Accounting\Entities\AccJournalEntry;
use Modules\Crm\Entities\CrmCompany;

class AstDepreciationRun extends Model
{
    protected $table = 'ast_depreciation_runs';

    protected $fillable = [
        'run_number',
        'company_id',
        'period_date',
        'total_depreciation',
        'assets_count',
        'journal_entry_id',
        'status',
        'details',
        'created_by',
    ];

    protected $casts = [
        'period_date' => 'date',
        'total_depreciation' => 'decimal:2',
        'assets_count' => 'integer',
        'details' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(CrmCompany::class, 'company_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(AccJournalEntry::class, 'journal_entry_id');
    }
}
