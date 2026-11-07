<?php

namespace Tests\Feature\Orcid;

use App\Models\ArticleAuthor;
use App\Models\JournalSetting;
use App\Models\OrcidCoauthorClaim;
use App\Models\OrcidConnection;
use App\Models\Submission;
use App\Models\User;
use App\Services\Orcid\OrcidId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * ORCID identity: OAuth linking, duplicate prevention, the co-author claim
 * flow, and the journal-level requirement switch.
 */
class OrcidIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private const ORCID = '0000-0002-1825-0097';

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Author', 'Editor', 'Reviewer', 'Super Admin', 'Managing Editor', 'Admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        config([
            'services.orcid.client_id' => 'test-client',
            'services.orcid.client_secret' => 'test-secret',
            'services.orcid.redirect' => 'http://localhost/orcid/callback',
        ]);

        JournalSetting::create([]);
        JournalSetting::current()->update(['orcid_enabled' => true]);
    }

    private function user(string $role = 'Author'): User
    {
        $user = User::factory()->create(['role' => $role]);
        $user->assignRole($role);

        return $user;
    }

    private function fakeTokenExchange(array $overrides = []): void
    {
        Http::fake([
            'https://orcid.org/oauth/token' => Http::response(array_merge([
                'access_token' => 'token-123',
                'token_type' => 'bearer',
                'refresh_token' => 'refresh-123',
                'expires_in' => 3600,
                'scope' => '/authenticate',
                'orcid' => self::ORCID,
                'name' => 'Ada Lovelace',
            ], $overrides)),
        ]);
    }

    private function startUserFlow(User $user): string
    {
        $this->actingAs($user)->post(route('orcid.connect'), ['return_to' => '/profile']);

        return (string) session('orcid.oauth.state');
    }

    public function test_orcid_checksum_validation_accepts_real_ids_and_rejects_typos(): void
    {
        $this->assertTrue(OrcidId::isValid(self::ORCID));
        $this->assertTrue(OrcidId::isValid('0000000218250097'));
        $this->assertFalse(OrcidId::isValid('0000-0002-1825-0098'));
        $this->assertFalse(OrcidId::isValid('1234'));
        $this->assertSame(self::ORCID, OrcidId::normalize(' 0000 0002 1825 0097 '));
    }

    public function test_successful_callback_links_a_verified_identity(): void
    {
        $this->fakeTokenExchange();
        $user = $this->user();
        $state = $this->startUserFlow($user);

        $response = $this->actingAs($user)->get(route('orcid.callback', [
            'code' => 'auth-code',
            'state' => $state,
        ]));

        $response->assertRedirect('/profile');
        $response->assertSessionHas('success');

        $this->assertSame(self::ORCID, $user->fresh()->orcid);
        $this->assertTrue($user->fresh()->hasVerifiedOrcid());

        $connection = OrcidConnection::where('user_id', $user->id)->firstOrFail();
        $this->assertSame(OrcidConnection::STATUS_CONNECTED, $connection->status);
        $this->assertSame('token-123', $connection->access_token);
        $this->assertSame('refresh-123', $connection->refresh_token);
    }

    public function test_a_state_mismatch_does_not_link_an_identity(): void
    {
        $this->fakeTokenExchange();
        $user = $this->user();
        $this->startUserFlow($user);

        $response = $this->actingAs($user)->get(route('orcid.callback', [
            'code' => 'auth-code',
            'state' => 'not-the-state',
        ]));

        $response->assertRedirect('/profile');
        $response->assertSessionHas('orcid_error');
        $this->assertNull($user->fresh()->orcid);
        $this->assertDatabaseCount('orcid_connections', 0);
    }

    public function test_the_same_orcid_cannot_verify_two_accounts(): void
    {
        $this->fakeTokenExchange();

        $first = $this->user();
        $state = $this->startUserFlow($first);
        $this->actingAs($first)->get(route('orcid.callback', ['code' => 'c1', 'state' => $state]))
            ->assertSessionHas('success');

        $second = $this->user();
        $state = $this->startUserFlow($second);
        $response = $this->actingAs($second)->get(route('orcid.callback', ['code' => 'c2', 'state' => $state]));

        $response->assertSessionHas('orcid_error');
        $this->assertNull($second->fresh()->orcid);
        $this->assertDatabaseCount('orcid_connections', 1);
    }

    public function test_co_author_claim_verifies_the_byline_without_touching_the_submitter(): void
    {
        $this->fakeTokenExchange();

        $author = $this->user();
        $submission = Submission::factory()->create(['author_id' => $author->id]);
        $coAuthor = ArticleAuthor::create([
            'submission_id' => $submission->id,
            'full_name' => 'Grace Hopper',
            'email' => 'grace@example.test',
            'institution' => 'US Navy',
            'author_order' => 2,
            'is_corresponding' => false,
        ]);

        $this->actingAs($author)
            ->post(route('author.submissions.coauthors.orcid-claim', [$submission, $coAuthor]))
            ->assertSessionHas('orcid_claim_url');

        $claim = OrcidCoauthorClaim::firstOrFail();
        $this->assertNull($claim->completed_at);

        $this->get(route('orcid.coauthor.show', ['token' => $claim->token]))
            ->assertOk()
            ->assertSee('Grace Hopper');

        Auth::logout();

        $this->post(route('orcid.coauthor.start', ['token' => $claim->token]))
            ->assertRedirectContains('orcid.org/oauth/authorize');

        $state = (string) session('orcid.oauth.state');

        $response = $this->get(route('orcid.callback', ['code' => 'coauthor-code', 'state' => $state]));

        $response->assertRedirect(route('orcid.coauthor.complete', ['name' => 'Grace Hopper']));

        $coAuthor->refresh();
        $this->assertSame(self::ORCID, $coAuthor->orcid);
        $this->assertNotNull($coAuthor->orcid_verified_at);
        $this->assertNull($coAuthor->user_id, 'A guest claim must not attach an account.');
        $this->assertNotNull($claim->fresh()->completed_at);
        $this->assertSame($author->id, $submission->fresh()->author_id);

        $this->get(route('orcid.coauthor.show', ['token' => $claim->token]))->assertStatus(410);
    }

    public function test_a_signed_in_claim_must_match_the_invited_email(): void
    {
        $this->fakeTokenExchange();

        $author = $this->user();
        $submission = Submission::factory()->create(['author_id' => $author->id]);
        $coAuthor = ArticleAuthor::create([
            'submission_id' => $submission->id,
            'full_name' => 'Grace Hopper',
            'email' => 'grace@example.test',
            'author_order' => 2,
            'is_corresponding' => false,
        ]);

        $this->actingAs($author)
            ->post(route('author.submissions.coauthors.orcid-claim', [$submission, $coAuthor]));

        $claim = OrcidCoauthorClaim::firstOrFail();
        $wrongUser = $this->user();

        $this->actingAs($wrongUser)->post(route('orcid.coauthor.start', ['token' => $claim->token]))
            ->assertRedirectContains('orcid.org/oauth/authorize');
        $state = (string) session('orcid.oauth.state');

        $this->actingAs($wrongUser)
            ->get(route('orcid.callback', ['code' => 'coauthor-code', 'state' => $state]))
            ->assertSessionHas('orcid_error');

        $this->assertNull($coAuthor->fresh()->orcid_verified_at);
        $this->assertNull($claim->fresh()->completed_at);
        $this->assertNull($wrongUser->fresh()->orcid);
    }

    public function test_required_orcid_blocks_a_new_submission_until_verified(): void
    {
        Storage::fake('private');

        JournalSetting::current()->update(['orcid_required_for_new_authors' => true]);

        $author = $this->user();

        $payload = [
            'title' => 'A study of ORCID gates',
            'abstract' => 'An abstract long enough to pass validation.',
            'manuscript' => UploadedFile::fake()->create('manuscript.pdf', 100, 'application/pdf'),
        ];

        $this->actingAs($author)
            ->post(route('author.submissions.store'), $payload)
            ->assertSessionHasErrors('orcid');

        $this->assertDatabaseCount('submissions', 0);

        $this->fakeTokenExchange();
        $state = $this->startUserFlow($author);
        $this->actingAs($author)->get(route('orcid.callback', ['code' => 'c', 'state' => $state]));

        $this->actingAs($author)
            ->post(route('author.submissions.store'), $payload)
            ->assertRedirect();

        $this->assertDatabaseCount('submissions', 1);
    }

    public function test_admin_can_render_the_orcid_screen_and_change_settings(): void
    {
        $admin = $this->user('Super Admin');

        $this->actingAs($admin)->get(route('admin.orcid.index'))
            ->assertOk()
            ->assertSee('ORCID Integration');

        $this->actingAs($admin)->put(route('admin.orcid.update'), [
            'orcid_enabled' => '1',
            'orcid_sandbox' => '0',
            'orcid_required_for_new_authors' => '1',
        ])->assertSessionHas('success');

        $setting = JournalSetting::current();
        $this->assertTrue($setting->orcid_enabled);
        $this->assertFalse($setting->orcid_sandbox);
        $this->assertTrue($setting->orcid_required_for_new_authors);
    }

    public function test_authors_cannot_open_the_orcid_admin_screen(): void
    {
        $this->actingAs($this->user())
            ->get(route('admin.orcid.index'))
            ->assertForbidden();
    }
}
