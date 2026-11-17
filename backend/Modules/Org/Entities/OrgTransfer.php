<?php

namespace Modules\Org\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Accounting\Entities\AccJournalEntry;
use Modules\Crm\Entities\CrmCompany;

class OrgTransfer extends Model
{
    protected $table = 'org_transfers';

    protected $fillable = [
        'transfer_number',
        'type',
        'from_company_id',
        'to_company_id',
        'from_branch_id',
        'to_branch_id',
        'from_warehouse_id',
        'to_warehouse_id',
        'transfer_date',
        'status',
        'items',
        'total_amount',
        'journal_entry_id',
        'notes',
        'created_by',
        'received_by',
        'received_at',
    ];

    protected $casts = [
        'transfer_date' => 'date',
        'items' => 'array',
        'total_amount' => 'decimal:2',
        'received_at' => 'datetime',
    ];

    public function fromCompany(): BelongsTo
    {
        return $this->belongsTo(CrmCompany::class, 'from_company_id');
    }

    public function toCompany(): BelongsTo
    {
        return $this->belongsTo(CrmCompany::class, 'to_company_id');
    }

    public function fromBranch(): BelongsTo
    {
        return $this->belongsTo(OrgBranch::class, 'from_branch_id');
    }

    public function toBranch(): BelongsTo
    {
        return $this->belongsTo(OrgBranch::class, 'to_branch_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(AccJournalEntry::class, 'journal_entry_id');
    }
}
