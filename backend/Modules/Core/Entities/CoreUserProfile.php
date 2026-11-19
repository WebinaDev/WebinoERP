<?php

namespace Modules\Core\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoreUserProfile extends Model
{
    protected $table = 'core_user_profiles';

    /** Fields a person may edit on their own profile. */
    public const SELF_FIELDS = [
        'national_id', 'birth_date', 'gender', 'father_name', 'phone_alt', 'landline',
        'province', 'city', 'address', 'postal_code', 'job_title', 'company',
        'emergency_contact_name', 'emergency_contact_phone', 'bio',
    ];

    protected $fillable = [
        'user_id', 'national_id', 'birth_date', 'gender', 'father_name', 'phone_alt', 'landline',
        'province', 'city', 'address', 'postal_code', 'job_title', 'company',
        'emergency_contact_name', 'emergency_contact_phone', 'bio', 'admin_notes', 'extra',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date:Y-m-d',
            'extra' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    public static function rules(): array
    {
        return [
            'national_id' => 'nullable|string|max:20',
            'birth_date' => 'nullable|date',
            'gender' => 'nullable|string|in:male,female,other',
            'father_name' => 'nullable|string|max:100',
            'phone_alt' => 'nullable|string|max:30',
            'landline' => 'nullable|string|max:30',
            'province' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:1000',
            'postal_code' => 'nullable|string|max:20',
            'job_title' => 'nullable|string|max:150',
            'company' => 'nullable|string|max:191',
            'emergency_contact_name' => 'nullable|string|max:150',
            'emergency_contact_phone' => 'nullable|string|max:30',
            'bio' => 'nullable|string|max:5000',
            'admin_notes' => 'nullable|string|max:5000',
            'extra' => 'nullable|array',
        ];
    }
}
