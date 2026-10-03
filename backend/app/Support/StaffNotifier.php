<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Modules\Core\Entities\CoreNotification;

class StaffNotifier
{
    /**
     * @param  array<string, mixed>  $extra
     */
    public static function notify(int $userId, string $type, string $title, string $body, array $extra = []): void
    {
        if ($userId <= 0) {
            return;
        }

        CoreNotification::query()->create([
            'user_id' => $userId,
            'type' => $type,
            'data' => array_merge([
                'title' => $title,
                'body' => $body,
            ], $extra),
            'is_read' => false,
        ]);

        $user = User::query()->find($userId);
        if (! $user || ! $user->email) {
            return;
        }

        try {
            Mail::raw($title."\n".$body, function ($message) use ($user, $title) {
                $message->to($user->email)->subject($title);
            });
        } catch (\Throwable) {
            // In-app notice still stands when mail is not configured.
        }
    }
}
