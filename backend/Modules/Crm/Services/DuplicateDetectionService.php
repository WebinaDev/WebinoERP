<?php

namespace Modules\Crm\Services;

use Illuminate\Support\Collection;
use Modules\Crm\Entities\CrmLead;

class DuplicateDetectionService
{
    /**
     * @return Collection<int, array{lead: CrmLead, confidence: float, reasons: array<int, string>}>
     */
    public function findDuplicates(CrmLead $lead, int $limit = 10): Collection
    {
        $candidates = CrmLead::query()
            ->where('id', '!=', $lead->id)
            ->whereNull('converted_at')
            ->limit(500)
            ->get();

        return $candidates
            ->map(function (CrmLead $other) use ($lead) {
                $confidence = 0.0;
                $reasons = [];

                if ($lead->email && $other->email && strcasecmp($lead->email, $other->email) === 0) {
                    $confidence += 0.5;
                    $reasons[] = 'email';
                }
                if ($lead->mobile && $other->mobile && $this->normalizePhone($lead->mobile) === $this->normalizePhone($other->mobile)) {
                    $confidence += 0.35;
                    $reasons[] = 'mobile';
                }
                $nameA = trim($lead->first_name.' '.$lead->last_name);
                $nameB = trim($other->first_name.' '.$other->last_name);
                if ($nameA && $nameB && similar_text(mb_strtolower($nameA), mb_strtolower($nameB), $pct) && $pct > 85) {
                    $confidence += 0.25;
                    $reasons[] = 'name';
                }
                if ($lead->company && $other->company && strcasecmp($lead->company, $other->company) === 0) {
                    $confidence += 0.15;
                    $reasons[] = 'company';
                }

                return [
                    'lead' => $other,
                    'confidence' => min(1.0, $confidence),
                    'reasons' => $reasons,
                ];
            })
            ->filter(fn (array $row) => $row['confidence'] >= 0.35)
            ->sortByDesc('confidence')
            ->take($limit)
            ->values();
    }

    public function merge(CrmLead $primary, CrmLead $duplicate): CrmLead
    {
        $fillable = ['topic', 'first_name', 'last_name', 'company', 'email', 'mobile', 'phone', 'description'];
        foreach ($fillable as $field) {
            if (empty($primary->{$field}) && ! empty($duplicate->{$field})) {
                $primary->{$field} = $duplicate->{$field};
            }
        }
        $primary->save();
        $duplicate->delete();

        return $primary->fresh();
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? $phone;
    }
}
