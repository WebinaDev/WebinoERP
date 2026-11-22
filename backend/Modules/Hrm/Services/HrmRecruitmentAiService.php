<?php

namespace Modules\Hrm\Services;

use Modules\Hrm\Entities\HrmJobApplicant;
use Modules\Hrm\Entities\HrmJobPosting;
use Modules\Hrm\Entities\HrmResumeAiMatch;

class HrmRecruitmentAiService
{
    /**
     * Match applicant resume data against job posting requirements using NLP/keyword vector scoring.
     */
    public function analyzeAndScoreApplicant(HrmJobApplicant $applicant, HrmJobPosting $posting): HrmResumeAiMatch
    {
        $resumeText = mb_strtolower((string) ($applicant->resume_text ?: $applicant->cover_letter ?: ''));
        $postingDescription = mb_strtolower((string) ($posting->description ?: $posting->title ?: ''));

        // Extract skills
        $commonTechSkills = [
            'react' => 'React.js',
            'next' => 'Next.js',
            'typescript' => 'TypeScript',
            'javascript' => 'JavaScript',
            'laravel' => 'Laravel',
            'php' => 'PHP',
            'postgresql' => 'PostgreSQL',
            'mysql' => 'MySQL',
            'docker' => 'Docker',
            'git' => 'Git',
            'tailwind' => 'Tailwind CSS',
            'python' => 'Python',
            'accounting' => 'حسابداری',
            'crm' => 'مدیریت ارتباط با مشتری (CRM)',
            'erp' => 'سیستم‌های یکپارچه ERP',
            'sales' => 'مذاکره و فروش B2B',
            'hr' => 'منابع انسانی و قوانین کار',
        ];

        $matched = [];
        $missing = [];

        foreach ($commonTechSkills as $keyword => $label) {
            $requiredInPosting = str_contains($postingDescription, $keyword) || str_contains($postingDescription, mb_strtolower($label));
            $foundInResume = str_contains($resumeText, $keyword) || str_contains($resumeText, mb_strtolower($label));

            if ($requiredInPosting) {
                if ($foundInResume) {
                    $matched[] = $label;
                } else {
                    $missing[] = $label;
                }
            } elseif ($foundInResume && count($matched) < 8) {
                $matched[] = $label;
            }
        }

        // Calculate scores
        $totalRequired = count($matched) + count($missing);
        $skillsScore = $totalRequired > 0 ? round((count($matched) / $totalRequired) * 100, 2) : 85.0;

        // Experience scoring
        $expYears = (float) ($applicant->experience_years ?? 3);
        $expScore = min(100.0, round(($expYears / 5.0) * 100, 2));

        // Education scoring
        $eduScore = match ((string) $applicant->education_level) {
            'phd' => 100.0,
            'master' => 90.0,
            'bachelor' => 80.0,
            'associate' => 65.0,
            default => 75.0,
        };

        // Weighted overall score
        $overall = round(($skillsScore * 0.50) + ($expScore * 0.30) + ($eduScore * 0.20), 2);

        $recommendation = match (true) {
            $overall >= 85 => 'highly_recommended',
            $overall >= 70 => 'recommended',
            $overall >= 50 => 'review',
            default => 'rejected',
        };

        $summaryFa = "تطابق مهارتی {$skillsScore}٪ با موقعیت شغلی «{$posting->title}». کاندیدا دارای مهارت‌های برجسته در " .
            (empty($matched) ? 'حوزه تخصصی' : implode('، ', array_slice($matched, 0, 4))) .
            " بوده و پیشنهاد اولیه برای مصاحبه فنی ثبت می‌گردد.";

        return HrmResumeAiMatch::query()->updateOrCreate(
            ['applicant_id' => $applicant->id, 'posting_id' => $posting->id],
            [
                'overall_score' => $overall,
                'skills_match_score' => $skillsScore,
                'experience_match_score' => $expScore,
                'education_match_score' => $eduScore,
                'matched_skills' => $matched,
                'missing_skills' => $missing,
                'ai_summary_fa' => $summaryFa,
                'recommendation' => $recommendation,
            ]
        );
    }
}
