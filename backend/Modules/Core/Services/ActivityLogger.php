<?php

namespace Modules\Core\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Entities\CoreActivity;

class ActivityLogger
{
    /**
     * @param  array<string,mixed>  $meta
     */
    public function log(string $action, Model $subject, ?int $userId = null, array $meta = []): CoreActivity
    {
        if ($userId === null) {
            $userId = Auth::id();
        }

        if ($userId === null && isset($subject->created_by)) {
            $userId = (int) $subject->created_by;
        }

        return CoreActivity::query()->create([
            'user_id' => $userId,
            'action' => $action,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'meta' => $meta,
        ]);
    }
}
