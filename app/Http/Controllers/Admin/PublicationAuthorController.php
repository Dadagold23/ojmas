<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ArticleAuthor;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class PublicationAuthorController extends Controller
{
    /**
     * Normalise ORCID either hyphenated or as 16 bare digits.
     * Returns null if the value is not a valid ORCID.
     */
    private function normaliseOrcid($value): ?string
    {
        $value = strtoupper(preg_replace('/\s+/', '', trim((string) $value)));

        if ($value === '' || $value === 'X') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{4}-\d{4}-\d{3}[0-9X]$/', $value) === 1) {
            return $value;
        }

        if (preg_match('/^\d{15}[0-9X]$/', $value) === 1) {
            return substr($value, 0, 4).'-'.substr($value, 4, 4).'-'.substr($value, 8, 4).'-'.substr($value, 12);
        }

        return null;
    }

    /**
     * Show the author correction interface for a published article.
     */
    public function edit(Article $article)
    {
        // Load required relationships
        $article->load(['submission', 'submission.articleAuthors', 'author']);

        // Check permissions - only admin/editor can edit
        abort_if(
            ! auth()->user()->hasAnyRole(['Admin', 'Managing Editor', 'Editor', 'Super Admin']),
            403,
            'Unauthorized action.'
        );

        // Get the submission or create a minimal one if needed
        $submission = $article->submission;

        // Build the current author list for the form
        $currentAuthors = [];

        // Add the lead author (from article->author or submission->author)
        $leadAuthorName = '';
        $leadAuthorEmail = '';
        $leadAuthorInstitution = '';
        $leadAuthorIsCorresponding = false;

        if ($article->author) {
            $leadAuthorName = $article->author->name ?? '';
            $leadAuthorEmail = $article->author->email ?? '';
            $leadAuthorInstitution = $article->author->institution ?? '';
            $leadAuthorIsCorresponding = true;
        } elseif ($submission && $submission->author) {
            $leadAuthorName = $submission->author->name ?? '';
            $leadAuthorEmail = $submission->author->email ?? '';
            $leadAuthorInstitution = $submission->author->institution ?? '';
            $leadAuthorIsCorresponding = true;
        }

        // Add existing co-authors from article_authors
        if ($submission && $submission->articleAuthors) {
            foreach ($submission->articleAuthors as $idx => $aa) {
                $currentAuthors[] = [
                    'order' => $aa->author_order,
                    'full_name' => $aa->full_name,
                    'email' => $aa->email,
                    'institution' => $aa->institution,
                    'country' => $aa->country,
                    'is_corresponding' => $aa->is_corresponding,
                    'id' => $aa->id,
                ];
            }
        }

        // If no co-authors exist yet but we have a lead author, add them as order 1
        if (empty($currentAuthors) && !empty($leadAuthorName)) {
            $currentAuthors[] = [
                'order' => 1,
                'full_name' => $leadAuthorName,
                'email' => $leadAuthorEmail,
                'institution' => $leadAuthorInstitution,
                'country' => null,
                'is_corresponding' => $leadAuthorIsCorresponding,
                'is_lead' => true,
            ];
        }

        // Get all editors for the "edited_by" field
        $editors = User::whereHas('roles', function ($q) {
            $q->whereIn('name', ['Admin', 'Managing Editor', 'Editor']);
        })->get(['id', 'name', 'email']);

        return view('admin.publication_authors.edit', compact(
            'article',
            'submission',
            'currentAuthors',
            'editors'
        ));
    }

    /**
     * Update the authors for a published article.
     */
    public function update(Request $request, Article $article)
    {
        abort_if(
            ! auth()->user()->hasAnyRole(['Admin', 'Managing Editor', 'Editor', 'Super Admin']),
            403,
            'Unauthorized action.'
        );

        $validated = $request->validate([
            'lead_author_name' => ['required', 'string', 'max:255'],
            'lead_author_email' => ['nullable', 'string', 'email', 'max:255'],
            'lead_author_institution' => ['nullable', 'string', 'max:255'],
            'lead_author_country' => ['nullable', 'string', 'max:100'],
            'correction_reason' => ['required', 'string'],
            'authors' => ['required', 'array'],
            'authors.*.order' => ['required', 'integer', 'min:1'],
            'authors.*.full_name' => ['required', 'string', 'max:255'],
            'authors.*.email' => ['nullable', 'string', 'email', 'max:255'],
            'authors.*.institution' => ['nullable', 'string', 'max:255'],
            'authors.*.country' => ['nullable', 'string', 'max:100'],
            'authors.*.is_corresponding' => ['nullable', 'boolean'],
            // ORCID fields (optional, for Crossref integration)
            'lead_author_orcid' => ['nullable', 'string', 'max:20'],
            'authors.*.orcid' => ['nullable', 'string', 'max:20'],
        ]);

        // Normalise ORCID values
        $validated['lead_author_orcid'] = $this->normaliseOrcid($validated['lead_author_orcid'] ?? null);

        // Normalise ORCID for each co-author
        foreach ($validated['authors'] as &$author) {
            $author['orcid'] = $this->normaliseOrcid($author['orcid'] ?? null);
        }
        unset($author);

        // Check if the authors array only contains the lead author (order 1)
        // If so, preserve existing co-authors instead of deleting them
        $authorsContainOnlyLead = !empty($validated['authors'])
            && count($validated['authors']) === 1
            && $validated['authors'][0]['order'] === 1;

        // Count existing co-authors (order > 1) before the transaction
        $existingCoAuthorCount = 0;
        if ($submission) {
            $existingCoAuthorCount = $submission->articleAuthors()
                ->where('author_order', '>', 1)
                ->count();
        }

        $submission = $article->submission;

        // Use a transaction to ensure atomicity
        DB::transaction(function () use ($submission, $validated, $article, $authorsContainOnlyLead, $existingCoAuthorCount) {
            // Handle the lead author (order 1)
            $leadAuthorData = [
                'full_name' => $validated['lead_author_name'],
                'email' => $validated['lead_author_email'] ?? null,
                'institution' => $validated['lead_author_institution'] ?? null,
                'country' => $validated['lead_author_country'] ?? null,
                'is_corresponding' => true,
                'author_order' => 1,
            ];

            // Remove any existing article authors and recreate
            // Unless the update only contains the lead author, in which case preserve existing co-authors
            if ($submission && !$authorsContainOnlyLead) {
                $submission->articleAuthors()->delete();

                // Create the lead author (order 1)
                $submission->articleAuthors()->create([
                    'full_name' => $leadAuthorData['full_name'],
                    'email' => $leadAuthorData['email'] ?? null,
                    'institution' => $leadAuthorData['institution'] ?? null,
                    'country' => $leadAuthorData['country'] ?? null,
                    'is_corresponding' => true,
                    'author_order' => 1,
                    'orcid' => $validated['lead_author_orcid'] ?? null,
                ]);

                // Create co-authors from the validated data
                foreach ($validated['authors'] as $author) {
                    // Skip the lead author (order 1) since we already created it
                    if ($author['order'] == 1) {
                        continue;
                    }

                    $submission->articleAuthors()->create([
                        'full_name' => $author['full_name'],
                        'email' => $author['email'] ?? null,
                        'institution' => $author['institution'] ?? null,
                        'country' => $author['country'] ?? null,
                        'is_corresponding' => $author['is_corresponding'] ?? false,
                        'author_order' => $author['order'],
                        'orcid' => $author['orcid'] ?? null,
                    ]);
                }
            } elseif ($submission && $authorsContainOnlyLead) {
                // Preserve existing co-authors - only update the lead author
                // The lead author data is already in the database; we just need to ensure it's correct
                // The existing co-authors remain untouched
            } else {
                // No submission - create article authors directly on the article
                // This handles the edge case
                ArticleAuthor::updateOrCreate(
                    ['article_id' => $article->id, 'author_order' => 1],
                    array_merge($leadAuthorData, ['submission_id' => null, 'orcid' => $validated['lead_author_orcid'] ?? null])
                );
            }

            // Record the correction audit log
            ArticleAuthor::where('submission_id', $submission?->id ?? null)
                ->orWhere('article_id', $article->id)
                ->each(function ($aa) use ($article, $validated) {
                    $aa->edited_by = auth()->id();
                    $aa->edited_at = now();
                    $aa->correction_reason = $validated['correction_reason'];
                    $aa->save();
                });
        });

        return back()
            ->with('success', 'Author information updated successfully.')
            ->with('correction_reason', $validated['correction_reason']);
    }

    /**
     * Remove a co-author from a published article.
     */
    public function destroy(Request $request, $authorId, Article $article)
    {
        abort_if(
            ! auth()->user()->hasAnyRole(['Admin', 'Managing Editor', 'Editor', 'Super Admin']),
            403,
            'Unauthorized action.'
        );

        $submission = $article->submission;

        // Check for confirmation token
        abort_if(
            !$request->filled('confirm_deletion'),
            400,
            'Confirmation required.'
        );

        // Find the author record
        $authorRecord = ArticleAuthor::where('submission_id', $submission?->id)
            ->orWhere('article_id', $article->id)
            ->find($authorId);

        if (!$authorRecord) {
            return back()
                ->with('error', 'Author record not found.');
        }

        // Don't allow removing the lead author (order 1) through this interface
        // - they would need to reassign via the lead author fields
        if ($authorRecord->author_order === 1) {
            return back()
                ->with('error', 'Cannot remove the lead author. Use the lead author fields to reassign.');
        }

        $authorName = $authorRecord->full_name;

        // Remove the author record
        $authorRecord->delete();

        return back()
            ->with('success', "Co-author '{$authorName}' removed successfully.");
    }

    /**
     * Reorder authors for a published article.
     */
    public function reorder(Request $request, Article $article)
    {
        abort_if(
            ! auth()->user()->hasAnyRole(['Admin', 'Managing Editor', 'Editor', 'Super Admin']),
            403,
            'Unauthorized action.'
        );

        $validated = $request->validate([
            'authors' => ['required', 'array'],
            'authors.*.id' => ['required', 'exists:article_authors,id'],
            'authors.*.order' => ['required', 'integer', 'min:1'],
        ]);

        $submission = $article->submission;

        DB::transaction(function () use ($submission, $validated) {
            foreach ($validated['authors'] as $author) {
                $submission->articleAuthors()
                    ->where('id', $author['id'])
                    ->update(['author_order' => $author['order']]);
            }
        });

        return back()
            ->with('success', 'Author order updated successfully.');
    }
}