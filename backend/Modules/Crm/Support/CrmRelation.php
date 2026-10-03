<?php

namespace Modules\Crm\Support;

use Modules\Crm\Entities\CrmAccount;
use Modules\Crm\Entities\CrmContact;
use Modules\Crm\Entities\CrmDeal;
use Modules\Crm\Entities\CrmLead;

class CrmRelation
{
    /** @var array<string, class-string> */
    public const MAP = [
        'account' => CrmAccount::class,
        'contact' => CrmContact::class,
        'deal' => CrmDeal::class,
        'lead' => CrmLead::class,
    ];

    public static function classFor(string $type): string
    {
        $key = strtolower($type);
        abort_unless(isset(self::MAP[$key]), 422, 'Unknown related type');

        return self::MAP[$key];
    }

    /**
     * @return list<string>
     */
    public static function storedNames(string $type): array
    {
        $class = self::classFor($type);

        return [$class, class_basename($class), $type];
    }
}
