<?php

namespace App\Http\Controllers;

use App\Models\PushNotification;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * In-site announcement centre (Addendum Module 3, §3.4).
 *
 * The fallback for visitors whose browser cannot receive Web Push: the same
 * announcements that were pushed, restricted to public audiences. Role
 * targeted notifications are internal and never appear here.
 */
class AnnouncementController extends Controller
{
    private const PUBLIC_AUDIENCES = ['all', 'editorial', 'promotions'];

    public function index(Request $request): View
    {
        $announcements = PushNotification::query()
            ->where('status', 'sent')
            ->whereIn('audience', self::PUBLIC_AUDIENCES)
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->paginate(20);

        return view('announcements.index', [
            'announcements' => $announcements,
            'labels' => PushNotification::CATEGORIES,
        ]);
    }
}
