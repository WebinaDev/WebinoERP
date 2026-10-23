<?php

namespace Modules\Crm\Events\Customer;

class ProjectCreated extends CustomerActivityEvent
{
    public const MODULE = 'projects';

    public const TYPE = 'project.created';

    public const SUBJECT = 'prj_project';

    public const TITLE = 'ایجاد پروژه';
}
