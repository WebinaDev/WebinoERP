<?php

namespace Modules\Crm\Entities;

use Illuminate\Database\Eloquent\Model;

class CrmListMember extends Model
{
    protected $table = 'crm_list_members';

    protected $fillable = ['list_id', 'member_type', 'member_id'];
}
