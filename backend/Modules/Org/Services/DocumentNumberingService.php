<?php

namespace Modules\Org\Services;

use Illuminate\Support\Facades\DB;
use Modules\Org\Entities\OrgDocumentSequence;

class DocumentNumberingService
{
    public function generateNextNumber(int $companyId, ?int $branchId, string $documentType, string $defaultPrefix = 'DOC-'): string
    {
        return DB::transaction(function () use ($companyId, $branchId, $documentType, $defaultPrefix) {
            $seq = OrgDocumentSequence::query()
                ->where('company_id', $companyId)
                ->where('branch_id', $branchId)
                ->where('document_type', $documentType)
                ->lockForUpdate()
                ->first();

            if (! $seq) {
                $seq = OrgDocumentSequence::query()->create([
                    'company_id' => $companyId,
                    'branch_id' => $branchId,
                    'document_type' => $documentType,
                    'prefix' => $defaultPrefix,
                    'next_number' => 1,
                    'padding_zeros' => 5,
                ]);
            }

            $currentNumber = $seq->next_number;
            $seq->increment('next_number');

            $padded = str_pad((string) $currentNumber, $seq->padding_zeros, '0', STR_PAD_LEFT);

            return "{$seq->prefix}{$padded}";
        });
    }
}
