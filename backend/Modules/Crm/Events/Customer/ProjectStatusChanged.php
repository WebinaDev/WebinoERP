<?php

namespace Modules\Crm\Events\Customer;

class ProjectStatusChanged extends CustomerActivityEvent
{
    public const MODULE = 'projects';

    public const TYPE = 'project.status_changed';

    public const SUBJECT = 'prj_project';

    public const TITLE = 'تغییر وضعیت پروژه';

    /** Several status changes per project are expected, so no dedupe key. */
    public function activity(): array
    {
        return parent::activity() + ['dedupe_key' => null];
    }
}
