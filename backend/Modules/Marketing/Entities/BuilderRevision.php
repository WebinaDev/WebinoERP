<?php

namespace Modules\Marketing\Entities;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuilderRevision extends Model
{
    protected $fillable = ['tenant_id', 'target_type', 'target_id', 'user_id', 'reason', 'document', 'size'];

    protected function casts(): array
    {
        return ['document' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
