<?php

namespace Modules\Marketing\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingFormSubmission extends Model
{
    protected $fillable = ['form_id', 'payload', 'locale'];

    protected $casts = ['payload' => 'array'];

    public function form(): BelongsTo
    {
        return $this->belongsTo(MarketingForm::class, 'form_id');
    }
}
