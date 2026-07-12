<?php

namespace Modules\Accounting\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccJournalEntry extends Model
{
    protected $table = 'acc_journal_entries';

    protected $fillable = [
        'fiscal_year_id',
        'document_no',
        'document_date',
        'description',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'document_date' => 'date',
        ];
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(AccFiscalYear::class, 'fiscal_year_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(AccJournalLine::class, 'journal_entry_id');
    }
}
