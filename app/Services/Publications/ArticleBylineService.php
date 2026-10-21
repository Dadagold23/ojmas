<?php

namespace App\Services\Publications;

use App\Models\Article;
use App\Models\ArticleAuthor;

/**
 * The single place the public journal resolves a published article's ordered
 * author list.
 *
 * Convention carried over from the submission form (unchanged): the submitting
 * account is author order 1 and the corresponding author; `article_authors`
 * rows hold the co-authors from order 2 onward. The byline is therefore
 * [account author (order 1)] + [article_authors rows sorted by author_order],
 * with two honest safeguards:
 *
 *  - a co-author row that carries the submitting account's own e-mail is the
 *    same person listed twice, so the duplicate row is skipped;
 *  - an article with no `article_authors` rows at all still gets a byline —
 *    the submitting account alone — instead of an empty, embarrassing list.
 *
 * Nothing here invents metadata: missing fields stay missing and the views
 * render honest placeholders.
 */
class ArticleBylineService
{
    /**
     * @return array<int, array{name: string, email: ?string, institution: ?string, country: ?string, orcid: ?string, is_corresponding: bool}>
     */
    public function for(Article $article): array
    {
        $article->loadMissing('author', 'submission.articleAuthors');

        $byline = [];
        $seen = [];

        $accountAuthor = $article->author;

        if ($accountAuthor !== null) {
            $name = trim((string) $accountAuthor->name);
            $seen[$this->normaliseEmail($accountAuthor->email)] = true;

            $byline[] = [
                'name' => $name !== '' ? $name : 'Author name not recorded',
                'email' => $accountAuthor->email,
                'institution' => $accountAuthor->institution ?? null,
                'country' => null,
                'orcid' => $accountAuthor->orcid ?? null,
                'is_corresponding' => true,
            ];
        }

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
                    'institution' => $row->institution,
                    'country' => $row->country,
                    'orcid' => $row->orcid,
                    'is_corresponding' => (bool) $row->is_corresponding,
                ];
            }
        }

        if ($byline === []) {
            $byline[] = [
                'name' => 'Author name not recorded',
                'email' => null,
                'institution' => null,
                'country' => null,
                'orcid' => null,
                'is_corresponding' => false,
            ];
        }

        return $byline;
    }

    private function normaliseEmail(?string $email): ?string
    {
        $email = strtolower(trim((string) $email));

        return $email !== '' ? $email : null;
    }
}
