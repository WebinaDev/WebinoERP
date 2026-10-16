<?php

namespace Modules\Marketing\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Marketing\Entities\MarketingAcademyChapter;
use Modules\Marketing\Entities\MarketingAcademyCourse;
use Modules\Marketing\Entities\MarketingAcademyLesson;

/**
 * Full LMS content (chapters, lessons with durations, quizzes, instructor profile, materials...) for the
 * Webina Academy courses. Idempotent: course rows are upserted by slug (title/cover kept), syllabus is replaced.
 *   php artisan db:seed --class="Modules\Marketing\Database\Seeders\AcademyLmsSeeder" --force
 */
class AcademyLmsSeeder extends Seeder
{
    public function run(): void
    {
        $courses = json_decode((string) file_get_contents(__DIR__.'/data/academy-lms.json'), true) ?: [];
        $fa = fn (int $n) => strtr((string) $n, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);

        DB::transaction(function () use ($courses, $fa) {
            foreach ($courses as $c) {
                $course = MarketingAcademyCourse::query()->where('slug', $c['slug'])->first();
                if (! $course) {
                    continue;
                }
                $mins = 0;
                foreach ($c['chapters'] as $ch) {
                    foreach ($ch['lessons'] as $l) {
                        $mins += (int) $l['minutes'];
                    }
                }
                $h = intdiv($mins, 60);
                $m = $mins % 60;
                $course->fill([
                    'description' => $c['description'],
                    'level' => $c['level'],
                    'duration' => $fa($h).' ساعت'.($m ? ' و '.$fa($m).' دقیقه' : ''),
                    'instructor' => $c['instructor'],
                    'outcomes' => $c['outcomes'],
                    'is_free' => (bool) $c['is_free'],
                    'published' => true,
                    'lms' => $c['lms'] + ['total_minutes' => $mins],
                ])->save();

                $course->lessons()->delete();
                $course->chapters()->delete();
                $order = 0;
                foreach ($c['chapters'] as $ci => $ch) {
                    $chapter = MarketingAcademyChapter::create([
                        'course_id' => $course->id, 'title' => $ch['title'], 'summary' => $ch['summary'], 'sort_order' => ($ci + 1) * 10,
                    ]);
                    foreach ($ch['lessons'] as $li => $l) {
                        MarketingAcademyLesson::create([
                            'course_id' => $course->id,
                            'chapter_id' => $chapter->id,
                            'slug' => 'c'.($ci + 1).'-l'.($li + 1),
                            'title' => $l['title'],
                            'summary' => $l['summary'],
                            'duration_minutes' => (int) $l['minutes'],
                            'kind' => $l['kind'],
                            'is_preview' => (bool) $l['preview'],
                            'quiz' => $l['quiz'] ?? null,
                            'sort_order' => ++$order * 10,
                            'published' => true,
                        ]);
                    }
                }
            }
        });
    }
}
