<?php

namespace Modules\Projects\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PrjKanbanBoard extends Model
{
    protected $table = 'prj_kanban_boards';

    protected $fillable = [
        'owner_type',
        'owner_id',
        'name',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function columns(): HasMany
    {
        return $this->hasMany(PrjKanbanColumn::class, 'board_id')->orderBy('sort_order');
    }
}
