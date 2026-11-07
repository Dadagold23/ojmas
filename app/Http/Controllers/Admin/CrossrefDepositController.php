<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Services\Publications\CrossrefDepositService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class CrossrefDepositController extends Controller
{
    /**
     * @var CrossrefDepositService
     */
    protected $crossrefService;

    /**
     * Create a new controller instance.
     */
    public function __construct(CrossrefDepositService $crossrefService)
    {
        $this->crossrefService = $crossrefService;
    }

    /**
     * Trigger Crossref metadata deposition for a published article.
     */
    public function deposit(Request $request, Article $article)
    {
        // Check permissions
        abort_unless(
            auth()->user()->hasAnyRole(['Admin', 'Super Admin']),
            403,
            'Unauthorized action. Only Super Admins can redeposit to Crossref.'
        );

        // Validate that the article has a DOI
        if (empty($article->doi)) {
            return back()
                ->with('error', 'This article has no DOI registered. Crossref deposition cannot proceed.')
                ->with('article_id', $article->id);
        }

        // Check for confirmation
        abort_if(
            !$request->filled('confirm_deposit'),
            400,
            'Confirmation required.'
        );

        // Perform the deposition
        $result = $this->crossrefService->deposit($article);

        if ($result['status'] === 'success') {
            return back()
                ->with('success', "Crossref metadata successfully deposited for '{$article->title}'. "
                    . "Event ID: {$result['event_id']}. DOI preserved: {$article->doi}.")
                ->with('deposit_event_id', $result['event_id']);
        } else {
            // Log the error for review
            Log::error('Crossref deposit failed', [
                'article_id' => $article->id,
                'doi' => $article->doi,
                'error' => $result['error'],
                'details' => $result['details'] ?? null,
                'initiated_by' => auth()->id(),
            ]);

            return back()
                ->with('error', "Crossref deposition failed: {$result['message']}. "
                    . "Check the deposit log for more details.")
                ->with('deposit_error', true);
        }
    }

    /**
     * Show the Crossref deposition confirmation form.
     */
    public function show(Article $article)
    {
        abort_unless(
            auth()->user()->hasAnyRole(['Admin', 'Super Admin']),
            403,
            'Unauthorized action.'
        );

        // Check if article is eligible for deposition
        $eligible = !empty($article->doi);

        // Check recent deposit attempts
        $recentDeposits = \App\Models\CrossrefDepositLog::where('article_id', $article->id)
            ->latest('initiated_at')
            ->take(5)
            ->get();

        return view('admin.crossref_deposit.show', compact(
            'article',
            'eligible',
            'recentDeposits'
        ));
    }
}