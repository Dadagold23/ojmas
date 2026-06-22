<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $report = [
            'authors' => User::role('Author')->count(),
            'reviewers' => User::role('Reviewer')->count(),
            'revenue' => Payment::where('status', 'paid')->sum('amount'),
            'status_breakdown' => Submission::query()
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status')
                ->toArray(),
        ];

        return view('admin.reports.index', compact('report'));
    }
}
