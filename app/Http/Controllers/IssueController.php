<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Issue;
use Illuminate\View\View;

class IssueController extends Controller
{
    public function index(): view 
    {
        $issues = Issue::with(['volume', 'articles'])
        ->where('status', 'published')
        ->whereNotNull('published_on')
        ->orderByDesc('published_on')
        ->paginate(12);

        return view('issues', compact('issues'));
    }
    
    public function show(string $slug): view
    {
        $issues = Issue::with(['volume', 'articles.author'])
        ->where('slug', $slug)
        ->where('status', 'published')
        ->firstOrFail();

        return view('issue', compact('issue'));
    }
}
