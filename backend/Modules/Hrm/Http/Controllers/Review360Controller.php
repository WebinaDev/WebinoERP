<?php

namespace Modules\Hrm\Http\Controllers;

use App\Http\Controllers\Api\PaginatesApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Entities\HrmReview360;
use Modules\Hrm\Entities\HrmReview360Rating;
use Modules\Hrm\Support\HrmAccess;

class Review360Controller extends Controller
{
    use PaginatesApi;

    public function index(Request $request): JsonResponse
    {
        $q = HrmReview360::query()->with(['employee', 'ratings.rater'])->orderByDesc('id');
        if (! HrmAccess::seesAllStaff()) {
            $own = HrmAccess::employee()?->id ?? 0;
            $q->where(function ($inner) use ($own) {
                $inner->where('employee_id', $own)
                    ->orWhereHas('ratings', fn ($r) => $r->where('rater_employee_id', $own));
            });
        }

        return $this->paginatedResponse($q->paginate($this->perPage($request)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:hrm_employees,id',
            'cycle_id' => 'nullable|exists:hrm_performance_cycles,id',
            'due_date' => 'nullable|date',
            'raters' => 'nullable|array',
            'raters.*.employee_id' => 'required_with:raters|exists:hrm_employees,id',
            'raters.*.relationship' => 'nullable|in:self,manager,peer,report',
        ]);
        $review = HrmReview360::query()->create([
            'employee_id' => $data['employee_id'],
            'cycle_id' => $data['cycle_id'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'status' => 'open',
            'created_by' => $request->user()?->id,
        ]);
        $raters = $data['raters'] ?? [];
        if ($raters === []) {
            $raters[] = ['employee_id' => $data['employee_id'], 'relationship' => 'self'];
        }
        foreach ($raters as $rater) {
            $employee = HrmEmployee::query()->find($rater['employee_id']);
            HrmReview360Rating::query()->create([
                'review_id' => $review->id,
                'rater_employee_id' => $rater['employee_id'],
                'rater_user_id' => $employee?->user_id,
                'relationship' => $rater['relationship'] ?? 'peer',
                'status' => 'pending',
            ]);
        }

        return response()->json(['data' => $review->load(['employee', 'ratings.rater']), 'message' => '360 review opened'], 201);
    }

    public function submit(Request $request, HrmReview360Rating $rating): JsonResponse
    {
        $own = HrmAccess::employee();
        $userId = $request->user()?->id;
        $allowed = HrmAccess::canManageStaff()
            || ($own && $rating->rater_employee_id === $own->id)
            || ($userId && $rating->rater_user_id === $userId);
        abort_unless($allowed, 403);
        $data = $request->validate([
            'score' => 'required|integer|min:0|max:100',
            'feedback' => 'nullable|string',
        ]);
        $rating->update([
            'score' => $data['score'],
            'feedback' => $data['feedback'] ?? null,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
        $review = $rating->review;
        $scores = $review->ratings()->where('status', 'submitted')->pluck('score');
        $review->average_score = $scores->isEmpty() ? null : round((float) $scores->avg(), 2);
        if ($review->ratings()->where('status', 'pending')->doesntExist()) {
            $review->status = 'closed';
        }
        $review->save();

        $payload = $review->load(['employee', 'ratings.rater']);
        if (! HrmAccess::seesAllStaff()) {
            $payload = $this->redact($payload, $own?->id);
        }

        return response()->json(['data' => $payload, 'message' => 'Rating submitted']);
    }

    private function redact(HrmReview360 $review, ?int $employeeId): HrmReview360
    {
        $review->ratings->each(function (HrmReview360Rating $rating) use ($employeeId, $review) {
            $mine = $rating->rater_employee_id === $employeeId;
            $subject = $review->employee_id === $employeeId;
            if (! $mine && $subject) {
                $rating->feedback = null;
                $rating->rater_employee_id = null;
                $rating->setRelation('rater', null);
            }
        });

        return $review;
    }
}
