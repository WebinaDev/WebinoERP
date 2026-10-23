<?php

namespace Modules\Poetry\Entities;

use Illuminate\Database\Eloquent\Model;

class PoetryTerm extends Model
{
    public const KINDS = ['occasion', 'figure', 'style', 'meter'];

    protected $table = 'poetry_terms';

    protected $fillable = ['kind', 'slug', 'name', 'description', 'image', 'sort'];

    public function poems()
    {
        return $this->belongsToMany(PoetryPoem::class, 'poetry_poem_term', 'term_id', 'poem_id');
    }
}
