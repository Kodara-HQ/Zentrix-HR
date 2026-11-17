<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;

/** Each person's own in-app notifications. Ported from the /notifications routes and page. */
class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $unreadOnly = $request->boolean('unread');
        $mine = Notification::where('user_id', $request->user()->id);

        return view('notifications.index', [
            'items' => (clone $mine)->when($unreadOnly, fn ($q) => $q->whereNull('read_at'))->latest()->paginate(20)->withQueryString(),
            'unreadCount' => (clone $mine)->whereNull('read_at')->count(),
            'unreadOnly' => $unreadOnly,
        ]);
    }

    /** Marks one as read; with ?open=1 goes on to the page it points at. */
    public function read(Request $request, Notification $notification)
    {
        abort_unless($notification->user_id === $request->user()->id, 404);
        $notification->read_at ??= now();
        $notification->save();

        if ($request->boolean('open') && $notification->link && str_starts_with($notification->link, '/')) {
            return redirect($notification->link);
        }

        return back();
    }

    public function readAll(Request $request)
    {
        Notification::where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return back()->with('status', 'All notifications marked as read.');
    }
}
