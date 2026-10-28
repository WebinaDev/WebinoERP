<?php

namespace Modules\Poetry\Entities;

use Modules\Marketing\Entities\MarketingMedia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Standalone audio/video item (poem optional).  */
class PoetryMedia extends Model
{
    protected $table = 'poetry_media';

    public const KINDS = ['audio', 'video'];

    protected $fillable = ['title', 'kind', 'media_id', 'file_url', 'external_url', 'cover', 'occasion_id', 'poem_id', 'reciter', 'duration', 'sort', 'status'];

    protected $casts = ['sort' => 'integer', 'play_count' => 'integer', 'media_id' => 'integer', 'poem_id' => 'integer', 'occasion_id' => 'integer'];

    public function poem()
    {
        return $this->belongsTo(PoetryPoem::class, 'poem_id');
    }

    public function occasion()
    {
        return $this->belongsTo(PoetryTerm::class, 'occasion_id');
    }

    public function asset()
    {
        return $this->belongsTo(MarketingMedia::class, 'media_id');
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published');
    }

    /** Has something playable: an uploaded/direct file or an external post/embed link. */
    public function scopePlayable(Builder $q): Builder
    {
        return $q->where(fn ($w) => $w->whereNotNull('media_id')->orWhere(fn ($x) => $x->whereNotNull('file_url')->where('file_url', '!=', ''))
            ->orWhere(fn ($x) => $x->whereNotNull('external_url')->where('external_url', '!=', '')));
    }
}
