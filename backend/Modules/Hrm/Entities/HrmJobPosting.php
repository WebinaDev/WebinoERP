<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;

class HrmJobPosting extends Model
{
    protected $table = 'hrm_job_postings';

    protected $fillable = ['title', 'department', 'description', 'status', 'closes_at'];

    protected $casts = ['closes_at' => 'date'];
}
