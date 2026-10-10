<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminAlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request, AdminAlertService $alerts): View
    {
        $items = $alerts->all($request);

        return view('admin.notifications', [
            'alerts' => $items,
            'unreadCount' => $items->where('unread', true)->count(),
        ]);
    }

    public function markRead(Request $request, AdminAlertService $alerts): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'key' => ['required', 'string', 'max:80'],
        ]);

        $alerts->markRead($request, $validated['key']);
        $unread = $alerts->all($request)->where('unread', true)->count();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'unread' => $unread]);
        }

        return back()->with('status', 'Notification marked as read.');
    }

    public function markAllRead(Request $request, AdminAlertService $alerts): JsonResponse|RedirectResponse
    {
        $alerts->markAllRead($request);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'unread' => 0]);
        }

        return back()->with('status', 'All notifications marked as read.');
    }
}
