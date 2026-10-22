<?php

namespace App\Services\Planning;

use App\Models\AdCampaign;
use App\Models\IssueSchedule;
use App\Models\PushNotification;
use Illuminate\Support\Carbon;

/**
 * Addendum Module 5 — the schedule calendar. A plain, honest month grid:
 * every chip is a date actually recorded in the database, nothing is
 * interpolated. Items the active user may not read are simply not there.
 */
class ScheduleCalendarService
{
    /**
     * Six-week month matrix with the recorded planning dates for one month.
     *
     * @return array{weeks: array<int, array<int, array{date: Carbon, inMonth: bool, today: bool, events: array<int, array{label: string, kind: string, sub?: string}>>}>, month: Carbon, prev: array{y: int, m: int}, next: array{y: int, m: int}, label: string}
     */
    public function month(int $year, int $month): array
    {
        $first = Carbon::create($year, $month, 1)->startOfDay();
        $start = $first->copy()->startOfWeek(Carbon::MONDAY);
        $monthEnd = $first->copy()->endOfMonth();

        $events = $this->eventsBetween($start, $monthEnd);

        $weeks = [];
        $cursor = $start->copy();
        do {
            $week = [];
            for ($d = 0; $d < 7; $d++) {
                $week[] = [
                    'date' => $cursor->copy(),
                    'inMonth' => $cursor->month === $first->month,
                    'today' => $cursor->isToday(),
                    'events' => $this->eventsOn($events, $cursor->toDateString()),
                ];
                $cursor->addDay();
            }
            $weeks[] = $week;
        } while ($cursor->month === $first->month && $cursor->year === $first->year);

        return [
            'weeks' => $weeks,
            'month' => $first,
            'label' => $first->format('F Y'),
            'prev' => ['y' => $first->copy()->subMonth()->year, 'm' => $first->copy()->subMonth()->month],
            'next' => ['y' => $first->copy()->addMonth()->year, 'm' => $first->copy()->addMonth()->month],
        ];
    }

    /** All recorded dates that fall in the shown window, labeled by kind. */
    private function eventsBetween(Carbon $start, Carbon $end): array
    {
        $events = [];

        foreach (
            IssueSchedule::query()
                ->whereNotIn('status', [IssueSchedule::STATUS_CANCELLED])
                ->with('issue.volume', 'volume')
                ->where(function ($q) use ($start, $end) {
                    $q->whereBetween('announce_at', [$start, $end])
                        ->orWhereBetween('planned_publication_date', [$start, $end])
                        ->orWhereBetween('cfp_opens_at', [$start, $end])
                        ->orWhereBetween('submission_deadline', [$start, $end]);
                })
                ->get() as $schedule
        ) {
            $label = ($schedule->volume->volume_no !== null ? 'V'.$schedule->volume->volume_no : 'Vol.')
                .($schedule->issue ? '·Iss '.$schedule->issue->issue_no : '');
            if ($schedule->announce_at?->between($start, $end)) {
                $events[] = ['date' => $schedule->announce_at->toDateString(), 'label' => $label, 'kind' => 'announce', 'sub' => $schedule->title];
            }
            if ($schedule->planned_publication_date?->between($start, $end)) {
                $events[] = ['date' => $schedule->planned_publication_date->toDateString(), 'label' => $label, 'kind' => 'publication', 'sub' => $schedule->title];
            }
            if ($schedule->cfp_opens_at?->between($start, $end)) {
                $events[] = ['date' => $schedule->cfp_opens_at->toDateString(), 'label' => $label, 'kind' => 'cfp_open', 'sub' => $schedule->title];
            }
            if ($schedule->submission_deadline?->between($start, $end)) {
                $events[] = ['date' => $schedule->submission_deadline->toDateString(), 'label' => $label, 'kind' => 'deadline', 'sub' => $schedule->title];
            }
        }

        foreach (
            PushNotification::query()
                ->where('status', 'scheduled')
                ->whereBetween('scheduled_at', [$start, $end])
                ->get() as $notification
        ) {
            $events[] = ['date' => $notification->scheduled_at->toDateString(), 'label' => 'Push', 'kind' => 'push', 'sub' => $notification->title];
        }

        foreach (
            AdCampaign::query()
                ->whereIn('status', ['scheduled', 'published'])
                ->with('placement')
                ->where(function ($q) use ($start, $end) {
                    $q->whereBetween('starts_at', [$start, $end])
                        ->orWhereBetween('ends_at', [$start, $end]);
                })
                ->get() as $campaign
        ) {
            if ($campaign->starts_at?->between($start, $end)) {
                $events[] = ['date' => $campaign->starts_at->toDateString(), 'label' => 'Ad ▸', 'kind' => 'campaign_start', 'sub' => $campaign->title];
            }
            if ($campaign->ends_at?->between($start, $end)) {
                $events[] = ['date' => $campaign->ends_at->toDateString(), 'label' => 'Ad ◂ end', 'kind' => 'campaign_end', 'sub' => $campaign->title];
            }
        }

        return $events;
    }

    private function eventsOn(array $events, string $date): array
    {
        return array_values(array_filter($events, fn ($event) => $event['date'] === $date));
    }
}
