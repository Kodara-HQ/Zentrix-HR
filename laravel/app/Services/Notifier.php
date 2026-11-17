<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\NotificationSetting;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/** In-app and email notifications, ported from server/src/lib/notify.ts. */
class Notifier
{
    /** @param  array<string|null>  $userIds */
    public function notify(array $userIds, string $title, string $body, string $type, ?string $link = null, ?string $dedupeKey = null): void
    {
        $ids = array_values(array_unique(array_filter($userIds)));
        if (! $ids) {
            return;
        }
        $setting = NotificationSetting::where('event_key', $type)->first();

        if ($setting?->in_app ?? true) {
            foreach ($ids as $userId) {
                $key = $dedupeKey ? "{$dedupeKey}:{$userId}" : null;
                if ($key && Notification::where('dedupe_key', $key)->exists()) {
                    continue;
                }
                Notification::create(['user_id' => $userId, 'title' => $title, 'body' => $body, 'type' => $type, 'link' => $link, 'dedupe_key' => $key]);
            }
        }

        // Email goes out only when the event is switched on in settings and a mailer is configured (MAIL_MAILER).
        if (($setting?->email ?? false) && config('mail.default') !== 'log') {
            foreach (User::whereIn('id', $ids)->where('is_active', true)->whereNotNull('email')->get() as $user) {
                rescue(fn () => Mail::raw($body, fn ($message) => $message->to($user->email)->subject($title)));
            }
        }
    }
}
