<?php

namespace App\Http\Controllers;

use App\Models\AdCampaign;
use App\Services\Advertising\AdCampaignService;
use Illuminate\Http\Request;

/**
 * Public advertisement surface: honest click counting and the dedicated
 * Calls for Papers page. Impressions are counted server-side when a
 * placement renders; clicks go through this controller so the destination
 * is validated again at the moment of redirect.
 */
class AdPublicController extends Controller
{
    public function __construct(private AdCampaignService $campaigns) {}

    /**
     * Click-through: records the click, then sends the visitor to the
     * campaign's destination. Only http(s) destinations ever redirect —
     * the scheme is re-validated here, not just at save time.
     */
    public function click(Request $request, AdCampaign $campaign)
    {
        $destination = (string) $campaign->destination_url;

        $scheme = strtolower((string) parse_url($destination, PHP_URL_SCHEME));

        abort_if($destination === '' || ! in_array($scheme, ['http', 'https'], true), 404);

        $this->campaigns->recordEvent($campaign, \App\Models\AdEvent::CLICK, $request);

        return redirect()->away($destination, 302, [
            'Rel' => 'nofollow sponsored noopener',
        ]);
    }

    /**
     * The dedicated Calls for Papers page: current calls, upcoming issues and
     * sponsored content from the journal's recorded campaigns only.
     */
    public function callsForPapers(Request $request)
    {
        $calls = collect($this->campaigns->visibleForPlacement('calls_for_papers', $request))
            ->filter(fn (AdCampaign $c) => $c->type === 'call_for_papers')
            ->values();

        $issueAnnouncements = collect($this->campaigns->visibleForPlacement('calls_for_papers'))
            ->filter(fn (AdCampaign $c) => in_array($c->type, ['issue_announcement', 'publication_date'], true))
            ->values();

        $sponsored = collect($this->campaigns->visibleForPlacement('sponsored_section', $request));

        return view('calls-for-papers', compact('calls', 'issueAnnouncements', 'sponsored'));
    }
}
