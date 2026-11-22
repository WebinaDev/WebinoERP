<?php

namespace Modules\Hrm\Services;

use ZipArchive;

class HrmSsoDisketteValidatorService
{
    /**
     * Complete field specification of official Iranian SSO DSKKAR00 & DSKWOR00 dBase III structures
     */
    public const CERTIFIED_DSKKAR_SPEC = [
        'DSK_ID' => ['type' => 'C', 'len' => 10, 'required' => true],
        'DSK_NAME' => ['type' => 'C', 'len' => 100, 'required' => true],
        'DSK_FARM' => ['type' => 'C', 'len' => 100, 'required' => true],
        'DSK_ADRS' => ['type' => 'C', 'len' => 100, 'required' => false],
        'DSK_KIND' => ['type' => 'N', 'len' => 1, 'required' => true],
        'DSK_YY' => ['type' => 'N', 'len' => 2, 'required' => true],
        'DSK_MM' => ['type' => 'N', 'len' => 2, 'required' => true],
        'DSK_LISTNO' => ['type' => 'C', 'len' => 12, 'required' => true],
        'DSK_DISC' => ['type' => 'C', 'len' => 100, 'required' => false],
        'DSK_NUM' => ['type' => 'N', 'len' => 5, 'required' => true],
        'DSK_TDD' => ['type' => 'N', 'len' => 6, 'required' => true],
        'DSK_TROOZ' => ['type' => 'N', 'len' => 12, 'required' => true],
        'DSK_TMAH' => ['type' => 'N', 'len' => 12, 'required' => true],
        'DSK_TMAZ' => ['type' => 'N', 'len' => 12, 'required' => true],
        'DSK_TMASH' => ['type' => 'N', 'len' => 12, 'required' => true],
        'DSK_TTOTL' => ['type' => 'N', 'len' => 12, 'required' => true],
        'DSK_TBIME' => ['type' => 'N', 'len' => 12, 'required' => true],
        'DSK_TKOSO' => ['type' => 'N', 'len' => 12, 'required' => true],
        'DSK_BIC' => ['type' => 'N', 'len' => 12, 'required' => true],
        'DSK_RATE' => ['type' => 'N', 'len' => 5, 'required' => true],
        'DSK_PRATE' => ['type' => 'N', 'len' => 2, 'required' => false],
        'DSK_BIMH' => ['type' => 'N', 'len' => 12, 'required' => false],
        'MON_PYM' => ['type' => 'C', 'len' => 3, 'required' => true],
    ];

    public const CERTIFIED_DSKWOR_SPEC = [
        'DSW_ID' => ['type' => 'C', 'len' => 10, 'required' => true],
        'DSW_YY' => ['type' => 'N', 'len' => 2, 'required' => true],
        'DSW_MM' => ['type' => 'N', 'len' => 2, 'required' => true],
        'DSW_LISTNO' => ['type' => 'C', 'len' => 12, 'required' => true],
        'DSW_ID1' => ['type' => 'C', 'len' => 10, 'required' => true],
        'DSW_FNAME' => ['type' => 'C', 'len' => 100, 'required' => true],
        'DSW_LNAME' => ['type' => 'C', 'len' => 100, 'required' => true],
        'DSW_DNAME' => ['type' => 'C', 'len' => 100, 'required' => false],
        'DSW_IDNO' => ['type' => 'C', 'len' => 15, 'required' => false],
        'DSW_IDPLC' => ['type' => 'C', 'len' => 100, 'required' => false],
        'DSW_IDATE' => ['type' => 'C', 'len' => 8, 'required' => false],
        'DSW_BDATE' => ['type' => 'C', 'len' => 8, 'required' => false],
        'DSW_SEX' => ['type' => 'C', 'len' => 3, 'required' => true],
        'DSW_NAT' => ['type' => 'C', 'len' => 10, 'required' => false],
        'DSW_OCP' => ['type' => 'C', 'len' => 100, 'required' => false],
        'DSW_SDATE' => ['type' => 'C', 'len' => 8, 'required' => false],
        'DSW_EDATE' => ['type' => 'C', 'len' => 8, 'required' => false],
        'DSW_DD' => ['type' => 'N', 'len' => 2, 'required' => true],
        'DSW_ROOZ' => ['type' => 'N', 'len' => 12, 'required' => true],
        'DSW_MAH' => ['type' => 'N', 'len' => 12, 'required' => true],
        'DSW_MAZ' => ['type' => 'N', 'len' => 12, 'required' => true],
        'DSW_MASH' => ['type' => 'N', 'len' => 12, 'required' => true],
        'DSW_TOTL' => ['type' => 'N', 'len' => 12, 'required' => true],
        'DSW_BIME' => ['type' => 'N', 'len' => 12, 'required' => true],
        'DSW_PRATE' => ['type' => 'N', 'len' => 2, 'required' => false],
        'DSW_JOB' => ['type' => 'C', 'len' => 6, 'required' => false],
        'PER_NATCOD' => ['type' => 'C', 'len' => 10, 'required' => true],
    ];

    /**
     * Validate pre-export consistency across workshop and individual workers
     *
     * @param array<string, mixed> $header
     * @param list<array<string, mixed>> $rows
     * @return array{is_valid: bool, errors: list<string>, warnings: list<string>}
     */
    public function validatePackage(array $header, array $rows): array
    {
        $errors = [];
        $warnings = [];

        $workshopCode = trim((string) ($header['workshop_code'] ?? ''));
        if (strlen($workshopCode) !== 10 || ! ctype_digit($workshopCode)) {
            $errors[] = "کد کارگاه نامعتبر است ({$workshopCode}): باید دقیقاً ۱۰ رقم عددی باشد.";
        }

        foreach ($rows as $i => $row) {
            $line = $i + 1;
            $nat = (string) ($row['national_id'] ?? '');
            if (! \Modules\Hrm\Support\IranianNationalId::isValid($nat)) {
                $errors[] = "ردیف {$line} ({$row['first_name']} {$row['last_name']}): کد ملی نامعتبر است ({$nat}).";
            }

            $insNo = trim((string) ($row['insurance_no'] ?? ''));
            if ($insNo === '' || strlen($insNo) < 8 || strlen($insNo) > 10) {
                $warnings[] = "ردیف {$line}: شماره بیمه تأمین اجتماعی غیراستاندارد است ({$insNo}).";
            }

            $days = (int) ($row['days'] ?? 0);
            if ($days <= 0 || $days > 31) {
                $errors[] = "ردیف {$line}: روزهای کارکرد نامعتبر است ({$days} روز).";
            }
        }

        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }
}
