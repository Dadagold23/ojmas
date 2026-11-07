<?php

namespace Tests\Feature\PublicSite;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The release number is public on every visitor-facing module: the header
 * badge and a version meta tag, both from one config value.
 */
class PublicVersionTest extends TestCase
{
    use RefreshDatabase;

    /** Public pages that render through layouts.app with chrome. */
    public static function publicPages(): array
    {
        return [
            'home' => ['home'],
            'archives' => ['archives'],
            'guidelines' => ['guidelines'],
            'contact' => ['contact'],
            'editorial' => ['editorial'],
            'editorial board' => ['editorial.board'],
            'editorial advisers' => ['editorial.advisers'],
            'scope and policies' => ['scope.policies'],
            'call for papers' => ['call.for.papers'],
            'issues' => ['issues.index'],
            'calls for papers' => ['ads.calls'],
            'announcements' => ['announcements'],
            'login' => ['login'],
        ];
    }

    /**
     * @dataProvider publicPages
     */
    public function test_every_public_module_shows_the_release(string $routeName): void
    {
        $this->get(route($routeName))
            ->assertOk()
            ->assertSee('v2.3.0')
            ->assertSee('<meta name="version" content="2.3.0">', false);
    }

    public function test_the_release_comes_from_one_config_value(): void
    {
        config(['journal.version' => '9.9.9']);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('v9.9.9')
            ->assertSee('<meta name="version" content="9.9.9">', false)
            ->assertDontSee('v2.3.0');
    }

    public function test_the_registered_release_is_two_three_zero(): void
    {
        $this->assertSame('2.3.0', config('journal.version'));
    }

    public function test_the_archive_browse_and_article_pages_carry_the_release(): void
    {
        $volume = \App\Models\Volume::create([
            'title' => 'OJMAS Journal Collection',
            'slug' => 'ojmas-collection-'.uniqid(),
            'volume_no' => 3,
            'year' => '2026',
            'status' => 'published',
        ]);

        $issue = \App\Models\Issue::create([
            'volume_id' => $volume->id,
            'title' => 'March 2026 Edition',
            'slug' => 'march-2026-'.uniqid(),
            'issue_no' => 1,
            'status' => 'published',
            'published_on' => '2026-03-01',
            'description' => 'The first issue of 2026.',
        ]);

        $author = \App\Models\User::factory()->create();
        $submission = \App\Models\Submission::factory()->create([
            'author_id' => $author->id,
            'status' => 'published',
            'title' => 'A Versioned Manuscript',
        ]);

        $article = \App\Models\Article::create([
            'submission_id' => $submission->id,
            'author_id' => $author->id,
            'issue_id' => $issue->id,
            'title' => 'A Versioned Manuscript',
            'slug' => 'a-versioned-manuscript-'.uniqid(),
            'abstract' => 'Abstract.',
            'keywords' => 'version',
            'subject_area' => 'Computer Science',
            'published_on' => '2026-03-01',
            'status' => 'published',
        ]);

        // /issues must list the year, not merely avoid a crash.
        $this->get(route('issues.index'))
            ->assertOk()
            ->assertSee('v2.3.0')
            ->assertSee('>2026<', false);

        $this->get(route('issue.show', $issue))
            ->assertOk()
            ->assertSee('v2.3.0');

        // The article page hides the site chrome, so no badge renders there;
        // the meta tag is its release marker.
        $this->get(route('article.show', $article->slug))
            ->assertOk()
            ->assertSee('<meta name="version" content="2.3.0">', false);
    }
}
