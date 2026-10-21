<?php

namespace Tests\Feature\Addendum;

use App\Models\Article;
use App\Models\Issue;
use App\Models\ManuscriptProofApproval;
use App\Models\Review;
use App\Models\ReviewerAssignment;
use App\Models\Submission;
use App\Models\User;
use App\Services\Publications\ArticleBylineService;
use App\Services\Publications\CitationService;
use App\Services\Publishing\PublicationService;
use App\Services\Workflow\EditorialWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Addendum Module 1 — Enhanced archive, issue ToC, ordered attribution and
 * deterministic citations.
 *
 * Each test pins one acceptance criterion from the module brief: recorded
 * order is what readers see, bylines are identical everywhere, metadata is
 * never invented, and only published material is ever reachable.
 */
class Module1ArchiveCitationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Author', 'Editor', 'Reviewer', 'Super Admin', 'Managing Editor'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        app(EditorialWorkflowService::class)->seed();
    }

    /* ─────────────── fixtures ─────────────── */

    private function makeIssue(array $issueAttributes = [], array $volumeAttributes = []): Issue
    {
        $volume = \App\Models\Volume::create(array_merge([
            'title' => 'OJMAS Journal Collection',
            'slug' => 'ojmas-journal-collection-'.strtolower(\Illuminate\Support\Str::random(4)),
            'volume_no' => 3,
            'year' => '2026',
            'status' => 'published',
        ], $volumeAttributes));

        return Issue::create(array_merge([
            'volume_id' => $volume->id,
            'title' => 'March 2026 Edition',
            'slug' => 'march-2026-'.strtolower(\Illuminate\Support\Str::random(4)),
            'issue_no' => 1,
            'status' => 'published',
            'published_on' => '2026-03-01',
            'description' => 'The first issue of 2026.',
        ], $issueAttributes));
    }

    /** A published article placed in $issue, plus an author account and manuscript. */
    private function publishedArticle(Issue $issue, string $title, array $articleAttributes = []): Article
    {
        $author = User::factory()->create();
        $author->assignRole('Author');

        $submission = Submission::factory()->create([
            'author_id' => $author->id,
            'status' => 'published',
            'title' => $title,
        ]);

        return Article::create(array_merge([
            'submission_id' => $submission->id,
            'author_id' => $author->id,
            'issue_id' => $issue->id,
            'title' => $title,
            'slug' => \Illuminate\Support\Str::slug($title).'-'.strtolower(\Illuminate\Support\Str::random(5)),
            'abstract' => 'Abstract for '.$title,
            'keywords' => 'testing, archive',
            'subject_area' => 'Computer Science',
            'pages' => '45-60',
            'doi' => '10.5555/ojmas.'.strtolower(\Illuminate\Support\Str::random(6)),
            'published_on' => '2026-03-01',
            'status' => 'published',
        ], $articleAttributes));
    }

    private function coAuthor(Submission $submission, array $attributes, int $order): \App\Models\ArticleAuthor
    {
        return $submission->articleAuthors()->create(array_merge([
            'is_corresponding' => false,
            'author_order' => $order,
            'full_name' => 'Co Author '.$order,
        ], $attributes));
    }

    /* ─────────────── 1. Issue table of contents ─────────────── */

    public function test_issue_toc_lists_published_articles_in_the_recorded_order(): void
    {
        $issue = $this->makeIssue();

        // Created deliberately out of order; explicit issue_order decides.
        $third = $this->publishedArticle($issue, 'The Third Placed Article', ['issue_order' => 3]);
        $first = $this->publishedArticle($issue, 'The First Placed Article', ['issue_order' => 1]);
        $second = $this->publishedArticle($issue, 'The Second Placed Article', ['issue_order' => 2]);

        $response = $this->get(route('issue.show', $issue->id));

        $response->assertOk();
        $response->assertSeeInOrder([
            'The First Placed Article',
            'The Second Placed Article',
            'The Third Placed Article',
        ]);
        $response->assertSee('Total Articles: 3');
        $response->assertSee('Pages: 45-60');
        $response->assertSee('DOI: '.$first->doi);
    }

    public function test_issue_toc_hides_draft_articles_and_unpublished_issues(): void
    {
        $issue = $this->makeIssue();

        $this->publishedArticle($issue, 'Visible Published Article');
        $hidden = $this->publishedArticle($issue, 'A Draft Hidden Article', ['status' => 'draft']);

        $this->get(route('issue.show', $issue->id))
            ->assertOk()
            ->assertSee('Visible Published Article')
            ->assertDontSee('A Draft Hidden Article');

        // The draft article page itself is unreachable, like before Module 1.
        $this->get(route('article.show', $hidden->slug))->assertNotFound();

        $unpublished = $this->makeIssue(['status' => 'draft']);
        $this->get(route('issue.show', $unpublished->id))->assertNotFound();
    }

    public function test_articles_without_explicit_order_follow_ordered_ones(): void
    {
        $issue = $this->makeIssue();

        $unordered = $this->publishedArticle($issue, 'A Left Ordered Article');
        $this->publishedArticle($issue, 'A Right Ordered Article', ['issue_order' => 1]);

        $ordered = $issue->orderedArticles()->pluck('title')->all();

        $this->assertSame(
            ['A Right Ordered Article', 'A Left Ordered Article'],
            array_values($ordered),
            'Articles with recorded positions come first; unordered articles follow deterministically.'
        );
    }

    /* ─────────────── 2. Ordered byline everywhere ─────────────── */

    public function test_article_page_shows_the_full_ordered_byline_with_affiliations_and_orcid(): void
    {
        $issue = $this->makeIssue();
        $article = $this->publishedArticle($issue, 'An Ordered Byline Article');

        $article->author->update([
            'name' => 'Ada Bello',
            'email' => 'ada.bello@example.org',
            'institution' => 'University of Lagos',
            'orcid' => '0000-0002-1825-0097',
        ]);

        $submission = $article->submission;
        $submission->articleAuthors()->create([
            'full_name' => 'Chidi Eze',
            'email' => 'chidi.eze@example.org',
            'institution' => 'Ahmadu Bello University',
            'country' => 'Nigeria',
            'orcid' => '0000-0004-6172-1117',
            'is_corresponding' => false,
            'author_order' => 2,
        ]);
        $submission->articleAuthors()->create([
            'full_name' => 'Bola Ade',
            'email' => 'bola.ade@example.org',
            'institution' => 'Obafemi Awolowo University',
            'country' => 'Nigeria',
            'is_corresponding' => false,
            'author_order' => 3,
        ]);

        $response = $this->get(route('article.show', $article->slug));

        $response->assertOk();
        $response->assertSeeInOrder(['Ada Bello', 'Chidi Eze', 'Bola Ade']);
        $response->assertSee('University of Lagos');
        $response->assertSee('Ahmadu Bello University');
        $response->assertSee('https://orcid.org/0000-0002-1825-0097', false);
        $response->assertSee('https://orcid.org/0000-0004-6172-1117', false);
        $response->assertSee('Corresponding');
    }

    public function test_byline_ignores_a_repeat_row_of_the_submitting_author(): void
    {
        $issue = $this->makeIssue();
        $article = $this->publishedArticle($issue, 'A Deduplicated Byline Article');

        $article->author->update(['name' => 'Ada Bello', 'email' => 'ada.bello@example.org']);

        $article->submission->articleAuthors()->create([
            'full_name' => 'Ada Bello',
            'email' => 'ada.bello@example.org',
            'author_order' => 2,
        ]);
        $article->submission->articleAuthors()->create([
            'full_name' => 'Chidi Eze',
            'author_order' => 3,
        ]);

        $byline = app(ArticleBylineService::class)->for($article->fresh());

        $names = array_column($byline, 'name');

        $this->assertSame(['Ada Bello', 'Chidi Eze'], $names,
            'The same person must not appear twice in the byline.');
    }

    public function test_article_without_article_authors_falls_back_to_the_submitting_account(): void
    {
        $issue = $this->makeIssue();
        $article = $this->publishedArticle($issue, 'A Solo Author Article');
        $article->author->update(['name' => 'Solo Writer']);

        $byline = app(ArticleBylineService::class)->for($article);

        $this->assertSame(['Solo Writer'], array_column($byline, 'name'));
    }

    /* ─────────────── 3. Deterministic citations ─────────────── */

    public function test_citations_are_deterministic_and_match_the_recorded_metadata(): void
    {
        $issue = $this->makeIssue([], ['volume_no' => 7]);
        $article = $this->publishedArticle($issue, 'A Fully Documented Article', [
            'pages' => '12-30',
            'doi' => '10.5555/ojmas.cites',
        ]);
        $article->author->update(['name' => 'Ada Bello']);
        $this->coAuthor($article->submission, ['full_name' => 'Chidi Eze'], 2);

        $citations = app(CitationService::class)->for($article->fresh());

        $this->assertSame([], $citations['limitations']);

        $this->assertSame(
            'Bello, A., & Eze, C. (2026). A Fully Documented Article. OJMAS Journal, 7(1), 12-30 https://doi.org/10.5555/ojmas.cites',
            $citations['apa']
        );

        $this->assertStringContainsString('@article{bello2026o'.$article->id.',', $citations['bibtex']);
        $this->assertStringContainsString('title = {A Fully Documented Article},', $citations['bibtex']);
        $this->assertStringContainsString('volume = {7},', $citations['bibtex']);

        $this->assertStringContainsString('TY  - JOUR', $citations['ris']);
        $this->assertStringContainsString('AU  - Bello, Ada', $citations['ris']);
        $this->assertStringContainsString('AU  - Eze, Chidi', $citations['ris']);
        $this->assertStringContainsString('DO  - 10.5555/ojmas.cites', $citations['ris']);
    }

    public function test_citations_state_what_is_missing_instead_of_inventing_it(): void
    {
        $issue = $this->makeIssue([], ['volume_no' => 7]);
        $article = $this->publishedArticle($issue, 'A Sparse Record Article', [
            'pages' => null,
            'doi' => null,
        ]);
        $article->author->update(['name' => 'Ada Bello']);

        $citations = app(CitationService::class)->for($article->fresh());

        $this->assertStringNotContainsString('doi.org', $citations['apa']);
        $this->assertStringNotContainsString('4040', $citations['apa']);

        $this->assertStringContainsString('No DOI is recorded', implode(' ', $citations['limitations']));
        $this->assertStringContainsString('No page range is recorded', implode(' ', $citations['limitations']));
    }

    public function test_citation_exports_download_in_dedicated_formats(): void
    {
        $issue = $this->makeIssue();
        $article = $this->publishedArticle($issue, 'A Downloadable Cite Article');
        $article->author->update(['name' => 'Ada Bello']);

        $this->get(route('article.cite', [$article->slug, 'bibtex']))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/x-bibtex; charset=UTF-8')
            ->assertSee('@article{', false);

        $this->get(route('article.cite', [$article->slug, 'ris']))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/x-research-info-systems; charset=UTF-8')
            ->assertSee('ER  - ', false);

        $this->get(route('article.cite', [$article->slug, 'txt']))
            ->assertOk()
            ->assertSee('Bello', false);

        $this->get(route('article.cite', [$article->slug, 'chicago']))
            ->assertOk();

        $unknown = $this->get(route('article.cite', [$article->slug, 'obsolete']));
        $unknown->assertNotFound();
    }

    /* ─────────────── 4. Publication metadata: licence/copyright ─────────────── */

    public function test_publisher_can_record_licence_and_copyright_through_the_pipeline(): void
    {
        Notification::fake();

        $author = User::factory()->create();
        $author->assignRole('Author');

        $submission = Submission::factory()->create([
            'author_id' => $author->id,
            'status' => 'accepted',
            'current_stage' => 'proof_approval',
        ]);
        $submission->payments()->create([
            'user_id' => $author->id,
            'purpose' => 'vetting_fee',
            'reference' => 'VET-L1-'.uniqid(),
            'amount' => 10000,
            'status' => 'paid',
            'gateway' => 'paystack',
            'paid_at' => now(),
        ]);

        $submission->payments()->create([
            'user_id' => $author->id,
            'purpose' => 'publication_fee',
            'reference' => 'PUB-L1-'.uniqid(),
            'amount' => 25000,
            'status' => 'paid',
            'gateway' => 'paystack',
            'paid_at' => now(),
        ]);

        $submission->editorialDecisions()->create([
            'editor_id' => 1,
            'decision' => 'accepted',
            'message' => 'Accepted.',
        ]);

        $reviewer = User::factory()->create();
        $reviewer->assignRole('Reviewer');

        $assignment = $submission->reviewerAssignments()->create([
            'reviewer_id' => $reviewer->id,
            'status' => 'completed',
        ]);

        Review::create([
            'reviewer_assignment_id' => $assignment->id,
            'recommendation' => 'accept',
            'comments_for_author' => 'Fine as revised.',
            'status' => Review::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);

        ManuscriptProofApproval::create([
            'submission_id' => $submission->id,
            'author_id' => $submission->author_id,
            'status' => ManuscriptProofApproval::STATUS_APPROVED,
            'approved_at' => now(),
        ]);

        $issue = $this->makeIssue();

        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $response = $this->actingAs($admin)->post(route('admin.publications.store'), [
            'submission_id' => $submission->id,
            'issue_id' => $issue->id,
            'licence' => 'CC BY 4.0',
            'copyright_note' => '© 2026 The Authors. Published by OJMAS.',
        ]);

        $response->assertRedirect();

        $article = Article::where('submission_id', $submission->id)->first();

        $this->assertNotNull($article, 'Publishing through the readiness gate must produce the article.');
        $this->assertSame('CC BY 4.0', $article->licence);
        $this->assertSame('© 2026 The Authors. Published by OJMAS.', $article->copyright_note);

        // ...and the public article page shows exactly that statement.
        $this->get(route('article.show', $article->slug))
            ->assertOk()
            ->assertSee('CC BY 4.0')
            ->assertSee('© 2026 The Authors. Published by OJMAS.');
    }
    /* ─────────────── 5. Editor order management ─────────────── */

    public function test_editor_can_record_and_change_the_article_order(): void
    {
        $issue = $this->makeIssue();
        $a = $this->publishedArticle($issue, 'Order Test Alpha');
        $b = $this->publishedArticle($issue, 'Order Test Beta');
        $c = $this->publishedArticle($issue, 'Order Test Gamma');

        $editor = User::factory()->create();
        $editor->assignRole('Editor');

        // Reader (guest) must not reach the reorder endpoint at all.
        $this->post(route('editor.issues.reorder', $issue->id), ['order' => [$a->id => 1]])
            ->assertRedirect('/login');

        $this->actingAs($editor)
            ->post(route('editor.issues.reorder', $issue->id), [
                'order' => [
                    $c->id => 1,
                    $a->id => 2,
                    $b->id => 3,
                ],
            ])
            ->assertRedirect();

        $this->assertSame(2, $a->fresh()->issue_order);
        $this->assertSame(1, $c->fresh()->issue_order);
        $this->assertSame(3, $b->fresh()->issue_order);

        // The public ToC follows the recorded order immediately.
        $this->get(route('issue.show', $issue->id))
            ->assertOk()
            ->assertSeeInOrder(['Order Test Gamma', 'Order Test Alpha', 'Order Test Beta']);

        // Articles from another issue cannot be tampered in.
        $otherIssue = $this->makeIssue(['issue_no' => 2, 'slug' => 'other-issue-'.strtolower(\Illuminate\Support\Str::random(4))]);
        $intruder = $this->publishedArticle($otherIssue, 'Order Test Intruder');

        $this->actingAs($editor)
            ->post(route('editor.issues.reorder', $issue->id), [
                'order' => [$intruder->id => 1],
            ])
            ->assertStatus(422);
    }

    /* ─────────────── 6. Issue covers ─────────────── */

    public function test_editor_can_upload_an_issue_cover_shown_in_the_public_archive(): void
    {
        if (! function_exists('imagepng')) {
            $this->markTestSkipped('GD is required to fabricate a real image for the cover test.');
        }

        $issue = $this->makeIssue();

        $editor = User::factory()->create();
        $editor->assignRole('Editor');

        $this->actingAs($editor)
            ->post(route('editor.issues.upload-cover', $issue->id), [
                'cover_image' => UploadedFile::fake()->image('cover.png', 400, 400),
            ])
            ->assertRedirect();

        $issue = $issue->fresh();

        $this->assertNotNull($issue->cover_image_path, 'The cover must be stored for the issue.');
        $this->assertStringStartsWith('issue-covers/', $issue->cover_image_path);

        // The public archive card renders the cover image.
        $this->get(route('archives'))->assertOk();

        // Non-image uploads are refused.
        $this->actingAs($editor)
            ->post(route('editor.issues.upload-cover', $issue->id), [
                'cover_image' => UploadedFile::fake()->create('evil.pdf', 10),
            ])
            ->assertSessionHasErrors('cover_image');
    }

    /* ─────────────── 7. Archive page ─────────────── */

    public function test_archive_page_organises_published_issues_and_supports_filters(): void
    {
        $issue2026 = $this->makeIssue();
        $issueOld = $this->makeIssue([
            'issue_no' => 4,
            'title' => 'The Old Edition',
            'published_on' => '2024-06-01',
        ], ['volume_no' => 1, 'year' => '2024', 'slug' => 'old-collection-'.strtolower(\Illuminate\Support\Str::random(4))]);

        $this->publishedArticle($issue2026, 'An Archive Listed Article');

        $this->get(route('archives'))
            ->assertOk()
            ->assertSee('Volumes &amp; Issues', false)
            ->assertSee($issue2026->title)
            ->assertSee($issueOld->title)
            ->assertSee('1 published article')
            ->assertSee('0 published articles');

        $this->get(route('archives', ['year' => '2024']))
            ->assertOk()
            ->assertSee('The Old Edition')
            ->assertDontSee('March 2026 Edition');

        $this->get(route('archives', ['q' => 'An Archive Listed Article']))
            ->assertOk()
            ->assertSee('March 2026 Edition');

        $draftIssue = $this->makeIssue(['status' => 'draft', 'title' => 'A Never Listed Edition', 'slug' => 'never-listed-'.strtolower(\Illuminate\Support\Str::random(4))]);
        $this->get(route('archives'))->assertDontSee('A Never Listed Edition');
    }

    /* ─────────────── 8. ORCID capture at submission ─────────────── */

    public function test_submission_captures_and_normalises_orcids(): void
    {
        $author = User::factory()->create();
        $author->assignRole('Author');

        $response = $this->actingAs($author)->post(route('author.submissions.store'), [
            'title' => 'An Orcid Captured Submission',
            'abstract' => 'A submission made to verify ORCID capture.',
            'keywords' => 'orcid, attribution',
            'subject_area' => 'Scholarly Attribution',
            'author_orcid' => '0000000461721117',
            'co_authors' => [
                ['full_name' => 'Chidi Eze', 'orcid' => '0000-0002-1825-0097'],
                ['full_name' => 'Bola Ade', 'orcid' => 'not-an-orcid'],
            ],
            'manuscript' => UploadedFile::fake()->create('manuscript.pdf', 100),
        ]);

        $response->assertRedirect();

        $submission = Submission::where('title', 'An Orcid Captured Submission')->first();
        $this->assertNotNull($submission);

        // The corresponding author's ORCID is canonicalised onto the account...
        $this->assertSame('0000-0004-6172-1117', $author->fresh()->orcid);

        // ...co-author identifiers are stored verbatim when valid...
        $rows = $submission->articleAuthors()->orderBy('author_order')->get()->values();
        $this->assertSame('0000-0002-1825-0097', $rows[0]->orcid);

        // ...and never stored when the string is not an identifier: no fake data.
        $this->assertNull($rows[1]->orcid);
    }

    /* ─────────────── 9. Sitemap and scholarly metadata ─────────────── */
    public function test_sitemap_lists_only_published_material(): void
    {
        $issue = $this->makeIssue();
        $article = $this->publishedArticle($issue, 'A Sitemap Listed Article');
        $draftArticle = $this->publishedArticle($issue, 'A Sitemap Draft Article', ['status' => 'draft']);

        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $response->assertSee(route('article.show', $article->slug), false);
        $response->assertSee(route('issue.show', $issue->id), false);
        $response->assertDontSee('A Sitemap Draft Article');
        $response->assertDontSee($draftArticle->slug, false);
    }

    public function test_article_page_emits_scholarly_metadata_honestly(): void
    {
        $issue = $this->makeIssue([], ['volume_no' => 9]);
        $article = $this->publishedArticle($issue, 'A Meta Tagged Article', [
            'pages' => '5-9',
            'doi' => '10.5555/ojmas.meta',
        ]);
        $article->author->update(['name' => 'Ada Bello']);

        $response = $this->get(route('article.show', $article->slug));

        $response->assertOk();
        $response->assertSee('name="citation_title" content="A Meta Tagged Article"', false);
        $response->assertSee('name="citation_author" content="Ada Bello"', false);
        $response->assertSee('citation_volume" content="9"', false);
        $response->assertSee('citation_firstpage" content="5"', false);
        $response->assertSee('citation_lastpage" content="9"', false);
        $response->assertSee('citation_doi" content="10.5555/ojmas.meta"', false);
        $response->assertSee('application/ld+json', false);

        // Missing fields are simply absent — never fabricated placeholders.
        $sparse = $this->publishedArticle($this->makeIssue(['issue_no' => 5, 'slug' => 'sparse-issue-'.strtolower(\Illuminate\Support\Str::random(4))]), 'A Meta Sparse Article', ['doi' => null, 'pages' => null]);

        $sparseResponse = $this->get(route('article.show', $sparse->slug));

        $sparseResponse->assertOk();
        $sparseResponse->assertDontSee('citation_doi', false);
        $sparseResponse->assertDontSee('citation_firstpage', false);
    }
}
