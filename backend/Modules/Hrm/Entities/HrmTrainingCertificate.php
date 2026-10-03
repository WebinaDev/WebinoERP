<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrmTrainingCertificate extends Model
{
    protected $table = 'hrm_training_certificates';

    protected $fillable = [
        'course_id', 'employee_id', 'enrollment_id', 'serial_no', 'issued_at',
        'html', 'pdf_path', 'signer_name',
    ];

    protected $casts = ['issued_at' => 'datetime'];

    public function course(): BelongsTo
    {
        return $this->belongsTo(HrmTrainingCourse::class, 'course_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrmEmployee::class, 'employee_id');
    }
}
