<?php

namespace App\Http\Controllers\Editor;

use App\Http\Controllers\Controller;
use App\Models\Submission;

class DashboardController extends Controller
{
    public function index()
    {
        $submissions = Submission::with(['author', 'files'])
            ->latest()
            ->take(10)
            ->get();

        $stats = [
            'assigned' => Submission::count(),
            'under_review' => Submission::where('status', 'under_review')->count(),
            'accepted' => Submission::where('status', 'accepted')->count(),
            'rejected' => Submission::where('status', 'rejected')->count(),
        ];

        $activities = [
            'New submission received.',
            'Reviewer assigned to a manuscript.',
            'An article was accepted for publication.',
        ];

        return view('editor.dashboard', compact('stats', 'activities', 'submissions'));
    }
}
