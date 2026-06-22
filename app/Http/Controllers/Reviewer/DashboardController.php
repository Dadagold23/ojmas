<?php

namespace App\Http\Controllers\Reviewer;

use App\Http\Controllers\Controller;
use App\Models\ReviewerAssignment;

class DashboardController extends Controller
{
    public function index()
    {
        $assignments = ReviewerAssignment::with(['submission.files'])
            ->where('reviewer_id', auth()->id())
            ->latest()
            ->take(10)
            ->get();

        $stats = [
            'active' => ReviewerAssignment::where('reviewer_id', auth()->id())->where('status', 'pending')->count(),
            'completed' => ReviewerAssignment::where('reviewer_id', auth()->id())->where('status', 'completed')->count(),
            'due_soon' => ReviewerAssignment::where('reviewer_id', auth()->id())
                ->whereDate('due_date', '<=', now()->addDays(7))
                ->count(),
        ];

        return view('reviewer.dashboard', compact('stats', 'assignments'));
    }
}
