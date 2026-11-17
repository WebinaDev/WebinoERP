<?php

namespace Modules\Procurement\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Accounting\Entities\AccPerson;

class PrcRfq extends Model
{
    protected $table = 'prc_rfqs';

    protected $fillable = [
        'rfq_number',
        'pr_id',
        'title',
        'status',
        'deadline_date',
        'vendor_ids',
        'bids',
        'winner_vendor_id',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'deadline_date' => 'date',
        'vendor_ids' => 'array',
        'bids' => 'array',
    ];

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PrcPurchaseRequest::class, 'pr_id');
    }

    public function winnerVendor(): BelongsTo
    {
        return $this->belongsTo(AccPerson::class, 'winner_vendor_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
