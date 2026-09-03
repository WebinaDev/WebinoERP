<?php

namespace Modules\Marketing\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingMedia extends Model
{
    protected $table = 'marketing_media';

    protected $fillable = ['folder_id', 'path', 'mime', 'alt', 'public_url', 'wp_id', 'meta'];

    protected $casts = ['meta' => 'array'];

    public function folder(): BelongsTo
    {
        return $this->belongsTo(MarketingMediaFolder::class, 'folder_id');
    }
}
