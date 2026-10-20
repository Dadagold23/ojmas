<?php

namespace Tests\Feature\Permissions;

use App\Services\Permissions\PermissionCatalog;
use App\Services\Permissions\PermissionRegistrar;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * §3.6: server-side authorization policies.
 *
 * The catalogue is the single source of truth for who may do what. These tests
 * pin the role boundaries that the module brief requires, especially the
 * Managing Editor ceiling (§3.4).
 */
class PermissionCatalogTest extends TestCase
{
    use RefreshDatabase;

    private PermissionRegistrar $registrar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registrar = app(PermissionRegistrar::class);
        $this->registrar->sync();
    }

    public function test_the_seed_is_idempotent_and_additive(): void
    {
        $first = $this->registrar->sync();

        $this->assertSame(0, $first['created_permissions']);
        $this->assertSame(0, $first['granted']);

        // Nothing is ever revoked by a re-sync.
        $admin = Role::where('name', 'Admin')->first();
        $this->assertTrue($admin->hasPermissionTo('publication.publish'));
    }

    public function test_every_user_in_the_database_keeps_their_access(): void
    {
        foreach (PermissionCatalog::roles() as $roleName) {
            $user = User::factory()->create();
            $user->assignRole($roleName);

            foreach (PermissionCatalog::roleMatrix()[$roleName] as $permission) {
                $this->assertTrue(
                    $user->can($permission),
                    sprintf('%s should hold %s.', $roleName, $permission),
                );
            }
        }
    }

    public function test_the_managing_editor_never_receives_the_prohibited_capabilities(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Managing Editor');

        foreach (PermissionCatalog::managingEditorProhibitions() as $prohibited) {
            $this->assertFalse(
                $user->can($prohibited),
                sprintf('Managing Editor must never hold %s.', $prohibited),
            );
        }

        // …but does hold the workflow coordination powers the brief assigns.
        $this->assertTrue($user->can('workflow.manage'));
        $this->assertTrue($user->can('workflow.assign.editor'));
        $this->assertTrue($user->can('workflow.deadlines'));
        $this->assertTrue($user->can('review.remind'));
    }

    public function test_super_admin_holds_every_permission(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Super Admin');

        foreach (PermissionCatalog::names() as $permission) {
            $this->assertTrue($user->can($permission), "Super Admin should hold {$permission}.");
        }
    }

    public function test_an_author_cannot_reach_editorial_or_review_powers(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Author');

        foreach ([
            'review.assign', 'review.view.assigned', 'decision.record', 'decision.communicate',
            'workflow.manage', 'workflow.transition', 'audit.view', 'audit.documents',
            'comment.view.editorial', 'comment.view.confidential', 'payment.view.any',
            'publication.publish', 'user.manage', 'settings.manage',
        ] as $denied) {
            $this->assertFalse($user->can($denied), "Author must not hold {$denied}.");
        }

        $this->assertTrue($user->can('submission.create'));
        $this->assertTrue($user->can('submission.respond.revision'));
        $this->assertTrue($user->can('manuscript.download'));
    }

    public function test_a_reviewer_cannot_reach_editorial_or_cross_submission_powers(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Reviewer');

        foreach ([
            'review.view.any', 'review.assign', 'decision.record', 'workflow.manage',
            'comment.view.author', 'submission.view.any', 'publication.publish', 'user.manage',
        ] as $denied) {
            $this->assertFalse($user->can($denied), "Reviewer must not hold {$denied}.");
        }

        $this->assertTrue($user->can('review.draft'));
        $this->assertTrue($user->can('review.submit'));
        $this->assertTrue($user->can('manuscript.download'));
    }

    public function test_an_admin_keeps_publish_rights_and_the_editor_gains_them_not(): void
    {
        // Deferred audit decision J7: Admin keeps publish rights in Module 3.
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $this->assertTrue($admin->can('publication.publish'));
        $this->assertTrue($admin->can('decision.record'));

        $editor = User::factory()->create();
        $editor->assignRole('Editor');
        $this->assertTrue($editor->can('decision.record'));
        $this->assertFalse($editor->can('publication.publish'));
    }

    public function test_abilities_still_resolve_without_the_seeder(): void
    {
        // A deployment that forgot to run the seeder must not lock staff out:
        // the role fallback keeps the boundaries the catalogue defines.
        Permission::query()->delete();

        $user = User::factory()->create();
        $user->assignRole('Editor');

        $this->assertTrue($user->can('decision.record'));
        $this->assertFalse($user->can('user.manage'));
    }

    public function test_unknown_permissions_are_resolved_through_spatie_only(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Super Admin');

        $this->assertFalse($user->can('not.a.real.permission'));
    }
}
