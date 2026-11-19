<?php

namespace Modules\Core\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A personal document (ID card, contract, certificate...) kept on the private disk. */
class CoreUserDocument extends Model
{
    protected $table = 'core_user_documents';

    public const TYPES = [
        'national_card', 'birth_certificate', 'passport', 'contract', 'education',
        'certificate', 'resume', 'insurance', 'military_service', 'bank', 'photo', 'other',
    ];

    protected $fillable = [
        'user_id', 'type', 'title', 'number', 'issued_at', 'expires_at', 'notes',
        'disk', 'path', 'original_name', 'mime_type', 'size_bytes', 'version', 'status', 'uploaded_by',
    ];

    protected $hidden = ['disk', 'path'];

    protected $appends = ['is_expired', 'expires_soon'];

    protected function casts(): array
    {
        return [
            'issued_at' => 'date:Y-m-d',
            'expires_at' => 'date:Y-m-d',
            'size_bytes' => 'integer',
            'version' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at !== null && $this->expires_at->endOfDay()->isPast();
    }

    public function getExpiresSoonAttribute(): bool
    {
        return $this->expires_at !== null
            && ! $this->is_expired
            && $this->expires_at->lte(now()->addDays(30));
    }
}
