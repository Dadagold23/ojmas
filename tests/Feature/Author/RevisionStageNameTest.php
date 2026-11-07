<?php

namespace Tests\Feature\Author;

use App\Models\EditorialStage;
use App\Models\Submission;
use App\Models\User;
use App\Services\Workflow\EditorialWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Regression for the 500 on GET /author/revisions/submissions/{id}:
 *
 *   LogicException: App\Models\Submission::currentStage must return a
 *   relationship instance.   (Submission::currentStageName)
 */
class RevisionStageNameTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Author', 'Editor', 'Super Admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        app(EditorialWorkflowService::class)->seed();
    }

    public function test_the_author_revision_page_names_the_stage_and_the_proof_link(): void
    {
        $author = User::factory()->create();
        $author->assignRole('Author');

        $submission = Submission::factory()->create([
            'author_id' => $author->id,
            'status' => 'accepted',
            'current_stage' => 'proof_approval',
        ]);

        $this->actingAs($author)
            ->get(route('author.revisions.show', $submission))
            ->assertOk()
            ->assertSee('Proofing and Author Approval')
            ->assertSee('Author approval for publication');
    }

    public function test_the_stage_name_falls_back_without_a_catalogue_row(): void
    {
        $submission = Submission::factory()->make(['current_stage' => 'unconfigured_stage']);
        $this->assertNull(EditorialStage::where('slug', 'unconfigured_stage')->first());
        $this->assertSame('unconfigured_stage', $submission->currentStageName());

        $submission->current_stage = null;
        $this->assertSame('Not yet staged', $submission->currentStageName());
    }
}
