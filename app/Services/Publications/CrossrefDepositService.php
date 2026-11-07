<?php

namespace App\Services\Publications;

use App\Models\Article;
use App\Models\ArticleAuthor;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Handles Crossref metadata deposition for published articles.
 *
 * After editorial corrections to author information, this service can be used
 * to redeposit updated metadata to Crossref, preserving the existing DOI while
 * updating author order, affiliations, and correspondence information.
 *
 * Convention: The article's DOI is assumed to already be registered with Crossref.
 * Only author-related metadata is updated; title, abstract, and other fields
 * are not modified through this endpoint to avoid Crossref policy violations.
 */
class CrossrefDepositService
{
    /**
     * Crossref API endpoint
     */
    private const CROSSREF_URL = 'https://api.crossref.org/works/';

    /**
     * Deposit updated metadata for an article's DOI.
     *
     * Only author-related fields are updated: author order, affiliations,
     * and corresponding author status. The DOI itself is preserved.
     *
     * @param  Article  $article
     * @return array {'status': string, 'message': string, 'doi': string, 'error': ?string}
     */
    public function deposit(Article $article): array
    {
        $doi = $article->doi;

        if (empty($doi)) {
            return [
                'status' => 'error',
                'message' => 'No DOI registered on this article.',
                'doi' => null,
                'error' => 'missing_doi',
            ];
        }

        // Build author list for Crossref
        $byline = $this->buildCrossrefByline($article);

        if ($byline === null) {
            return [
                'status' => 'error',
                'message' => 'No author data available for Crossref deposition.',
                'doi' => $doi,
                'error' => 'no_author_data',
            ];
        }

        // Prepare Crossref deposit payload
        $payload = [
            'author' => $byline,
        ];

        try {
            // First, check current metadata
            $current = Http::timeout(30)
                ->get(self::CROSSREF_URL . $doi, [
                    'headers' => [
                        'Accept' => 'application/vnd.crossref.unixref+xml',
                    ],
                ])
                ->json();

            $currentStatus = data_get($current, 'message.status');

            // Prepare deposit
            $depositPayload = [
                'DOI' => $doi,
                'timestamp' => now()->timestamp,
                'author' => $byline,
            ];

            // Send to Crossref
            $response = Http::timeout(60)
                ->withHeaders([
                    'Content-Type' => 'application/x-research-info-systems',
                    'User-Agent' => 'OJMAS/'.config('journal.version').' (https://ojmaspublish.online; admin@ojmaspublish.online)',
                ])
                ->post(self::CROSSREF_URL, $depositPayload);

            $status = $response->status();
            $body = $response->json();

            // Log the deposit
            CrossrefDepositLog::create([
                'article_id' => $article->id,
                'doi' => $doi,
                'payload' => json_encode($depositPayload),
                'response_status' => $status,
                'response_body' => json_encode($body),
                'initiated_by' => auth()->id(),
                'initiated_at' => now(),
            ]);

            if ($status >= 200 && $status < 300) {
                // Extract event ID from response
                $eventId = data_get($body, 'message.item.created');

                return [
                    'status' => 'success',
                    'message' => 'Metadata successfully deposited to Crossref.',
                    'doi' => $doi,
                    'error' => null,
                    'event_id' => $eventId,
                ];
            } else {
                return [
                    'status' => 'error',
                    'message' => 'Crossref returned error status: ' . $status,
                    'doi' => $doi,
                    'error' => 'crossref_error',
                    'details' => $body,
                ];
            }
        } catch (\Throwable $e) {
            // Log the error
            CrossrefDepositLog::create([
                'article_id' => $article->id,
                'doi' => $doi,
                'payload' => json_encode($payload ?? []),
                'response_status' => 0,
                'response_body' => $e->getMessage(),
                'initiated_by' => auth()->id(),
                'initiated_at' => now(),
            ]);

            return [
                'status' => 'error',
                'message' => 'Crossref deposit failed: ' . $e->getMessage(),
                'doi' => $doi,
                'error' => 'deposit_exception',
            ];
        }
    }

    /**
     * Build Crossref author list from article's article_authors.
     *
     * Convention: The submitting account is author order 1 and the corresponding author.
     * article_authors rows hold co-authors from order 2 onward.
     *
     * @param  Article  $article
     * @return array|null
     */
    private function buildCrossrefByline(Article $article): ?array
    {
        $article->loadMissing('author', 'submission.articleAuthors');

        $byline = [];
        $seen = [];

        // Lead author (from article->author or submission->author)
        $accountAuthor = $article->author;

        if ($accountAuthor !== null) {
            $name = trim((string) $accountAuthor->name);
            $email = $accountAuthor->email;
            $orcid = $accountAuthor->orcid;
            $institution = $accountAuthor->institution;

            $seen[$this->normaliseEmail($email)] = true;

            $byline[] = [
                'name' => $name !== '' ? $name : 'Author name not recorded',
                'email' => $email,
                'affiliation' => $institution ? ['@type' => 'Organization', 'name' => $institution] : null,
                'sequence' => 'first',
                'roles' => [' corresponding' => true],
                'ORCID' => $orcid ? 'https://orcid.org/' . $orcid : null,
            ];
        }

        // Co-authors from article_authors
        $coAuthors = $article->submission?->articleAuthors;

        if ($coAuthors !== null) {
            foreach ($coAuthors->sortBy([
                ['author_order', 'asc'],
                ['id', 'asc'],
            ]) as $row) {
                /** @var ArticleAuthor $row */
                $emailKey = $this->normaliseEmail($row->email);

                if ($emailKey !== null && isset($seen[$emailKey])) {
                    continue; // Same person as the submitting account, already in byline.
                }
                if ($emailKey !== null) {
                    $seen[$emailKey] = true;
                }

                $name = trim((string) $row->full_name);

                if ($name === '') {
                    continue; // Never render a blank name slot.
                }

                $byline[] = [
                    'name' => $name,
                    'email' => $row->email,
                    'affiliation' => $row->institution ? ['@type' => 'Organization', 'name' => $row->institution] : null,
                    'sequence' => 'additional',
                    'roles' => [' corresponding' => (bool) $row->is_corresponding],
                    'ORCID' => $row->orcid ? 'https://orcid.org/' . $row->orcid : null,
                ];
            }
        }

        if ($byline === []) {
            return null;
        }

        return $byline;
    }

    /**
     * Normalise email for deduplication.
     */
    private function normaliseEmail(?string $email): ?string
    {
        $email = strtolower(trim((string) $email));

        return $email !== '' ? $email : null;
    }
}