<?php

namespace Modules\Docs\Entities;

use Illuminate\Database\Eloquent\Model;

class DocsContract extends Model
{
    protected $table = 'docs_contracts';

    protected $fillable = ['title', 'party_name', 'status', 'body', 'meta', 'signed_at', 'created_by'];

    protected $casts = ['signed_at' => 'date', 'meta' => 'array'];
}
