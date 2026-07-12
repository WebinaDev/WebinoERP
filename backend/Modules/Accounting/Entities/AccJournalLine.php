<?php

namespace Modules\Accounting\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccJournalLine extends Model
{
    protected $table = 'acc_journal_lines';

    protected $fillable = [
        'journal_entry_id', 'account_id', 'debit', 'credit', 'description',
    ];

    protected function casts(): array
    {
        return [
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
        ];
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(AccJournalEntry::class, 'journal_entry_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(AccChartAccount::class, 'account_id');
    }
}
