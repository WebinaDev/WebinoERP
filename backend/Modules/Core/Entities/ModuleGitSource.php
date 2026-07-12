<?php

namespace Modules\Core\Entities;

use Illuminate\Database\Eloquent\Model;

class ModuleGitSource extends Model
{
    protected $table = 'module_git_sources';

    protected $fillable = [
        'slug',
        'clone_url',
        'auth_type',
        'credential_ref',
    ];
}
