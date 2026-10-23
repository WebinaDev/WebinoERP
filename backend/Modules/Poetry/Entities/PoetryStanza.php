<?php

namespace Modules\Poetry\Entities;

use Illuminate\Database\Eloquent\Model;

class PoetryStanza extends Model
{
    public const TYPES = ['title', 'stanza', 'refrain'];

    protected $table = 'poetry_stanzas';

    protected $fillable = ['poem_id', 'position', 'type', 'layout', 'repeat', 'note', 'lines'];

    protected $casts = ['lines' => 'array', 'repeat' => 'integer', 'position' => 'integer'];
}
