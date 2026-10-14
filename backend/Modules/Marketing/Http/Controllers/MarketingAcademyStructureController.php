<?php

namespace Modules\Marketing\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Marketing\Entities\MarketingAcademyChapter;
use Modules\Marketing\Entities\MarketingAcademyCourse;
use Modules\Marketing\Entities\MarketingAcademyLesson;

/**
 * Course structure (chapters → lessons) for the academy editor. Saving is explicit; new lessons are created
 * unpublished, existing ones keep their published flag. Nothing is deleted here.
 */
class MarketingAcademyStructureController extends Controller
{
    public function show(int $id): JsonResponse
    {
        $course = MarketingAcademyCourse::query()->findOrFail($id);

        return response()->json(['data' => $this->tree($course)]);
    }

    public function save(Request $request, int $id): JsonResponse
    {
        $course = MarketingAcademyCourse::query()->findOrFail($id);
        $data = $request->validate([
            'chapters' => 'present|array|max:40',
            'chapters.*.id' => 'nullable|integer',
            'chapters.*.title' => 'required|string|max:255',
            'chapters.*.summary' => 'nullable|string|max:5000',
            'chapters.*.lessons' => 'nullable|array|max:60',
            'chapters.*.lessons.*.id' => 'nullable|integer',
            'chapters.*.lessons.*.title' => 'required|string|max:255',
            'chapters.*.lessons.*.summary' => 'nullable|string|max:5000',
            'chapters.*.lessons.*.content' => 'nullable|string|max:60000',
        ]);

        DB::transaction(function () use ($course, $data) {
            $order = 0;
            foreach ($data['chapters'] as $ci => $c) {
                $chapter = ! empty($c['id']) ? MarketingAcademyChapter::query()->where('course_id', $course->id)->findOrFail($c['id']) : new MarketingAcademyChapter(['course_id' => $course->id]);
                $chapter->fill(['title' => $c['title'], 'summary' => $c['summary'] ?? null, 'sort_order' => $ci + 1])->save();
                foreach ($c['lessons'] ?? [] as $l) {
                    $order++;
                    $lesson = ! empty($l['id']) ? MarketingAcademyLesson::query()->where('course_id', $course->id)->findOrFail($l['id']) : new MarketingAcademyLesson(['course_id' => $course->id, 'published' => false]);
                    $lesson->fill([
                        'chapter_id' => $chapter->id, 'title' => $l['title'], 'summary' => $l['summary'] ?? $lesson->summary,
                        'content' => array_key_exists('content', $l) ? $l['content'] : $lesson->content, 'sort_order' => $order,
                    ]);
                    if (! $lesson->slug) {
                        $lesson->slug = $this->uniqueSlug($course->id, $l['title'], $order);
                    }
                    $lesson->save();
                }
            }
        });

        return response()->json(['data' => $this->tree($course->fresh())]);
    }

    private function uniqueSlug(int $courseId, string $title, int $n): string
    {
        $base = Str::slug($title) ?: 'lesson-'.$n;
        $slug = $base;
        for ($i = 2; MarketingAcademyLesson::query()->where('course_id', $courseId)->where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }

    private function tree(MarketingAcademyCourse $course): array
    {
        $chapters = $course->chapters()->with('lessons')->get();
        $loose = MarketingAcademyLesson::query()->where('course_id', $course->id)->whereNull('chapter_id')->orderBy('sort_order')->get();

        return ['course' => $course->only(['id', 'title', 'description', 'level', 'duration', 'outcomes']), 'chapters' => $chapters, 'ungrouped_lessons' => $loose];
    }
}
