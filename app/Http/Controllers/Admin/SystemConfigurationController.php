<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DocumentRetentionPolicy;
use App\Models\EditorialStage;
use App\Models\JournalStyleProfile;
use App\Services\Permissions\PermissionCatalog;
use App\Services\Permissions\PermissionRegistrar;
use App\Services\Workflow\EditorialWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Super Admin configuration (§3.5).
 *
 * Role and permission management, journal style profiles, workflow settings,
 * document-processing settings and retention policy.
 *
 * These screens are gated on individual permissions rather than on a role
 * name, so a Managing Editor can be given, for example, workflow configuration
 * without thereby receiving user management.
 */
class SystemConfigurationController extends Controller
{
    public function __construct(
        private readonly PermissionRegistrar $permissions,
        private readonly EditorialWorkflowService $workflow,
    ) {
    }

    /** Role and permission matrix. */
    public function permissions(Request $request)
    {
        $this->authorize('manageRoles', new \App\Models\Submission);

        $roles = Role::where('guard_name', 'web')->orderBy('name')->get();

        return view('admin.configuration.permissions', [
            'roles' => $roles,
            'grouped' => PermissionCatalog::grouped(),
            'matrix' => PermissionCatalog::roleMatrix(),
            'prohibitions' => PermissionCatalog::managingEditorProhibitions(),
            'granted' => $roles->mapWithKeys(
                fn (Role $role) => [$role->name => $role->permissions->pluck('name')->all()],
            )->all(),
        ]);
    }

    public function updateRolePermissions(Request $request, Role $role)
    {
        $this->authorize('manageRoles', new \App\Models\Submission);

        $validated = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
        ]);

        $requested = (array) ($validated['permissions'] ?? []);
        $known = PermissionCatalog::names();

        $unknown = array_diff($requested, $known);

        abort_if($unknown !== [], 422, 'Unknown permission: '.implode(', ', $unknown));

        // A Managing Editor is never granted the unrestricted capabilities, even
        // if a POST asks for them. This is enforced here, not in the form.
        if ($role->name === 'Managing Editor') {
            $requested = array_diff($requested, PermissionCatalog::managingEditorProhibitions());
        }

        $role->syncPermissions($requested);

        app(Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        return back()->with('success', 'Permissions updated for '.$role->name.'.');
    }

    public function resync(Request $request)
    {
        $this->authorize('manageRoles', new \App\Models\Submission);

        $result = $this->permissions->sync();

        return back()->with('success', sprintf(
            'Permission catalogue synced: %d new permission(s), %d new role(s), %d granted.',
            $result['created_permissions'],
            $result['created_roles'],
            $result['granted'],
        ));
    }

    /** Journal formatting profiles. */
    public function styleProfiles(Request $request)
    {
        $this->authorize('manageStyleProfiles', new \App\Models\Submission);

        return view('admin.configuration.style-profiles', [
            'profiles' => JournalStyleProfile::orderBy('slug')->get(),
            'available' => PermissionCatalog::all(),
            'editor' => $this->workflow,
        ]);
    }

    public function saveStyleProfile(Request $request, JournalStyleProfile $profile)
    {
        $this->authorize('manageStyleProfiles', new \App\Models\Submission);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'description' => ['nullable', 'string', 'max:2000'],
            'version' => ['required', 'string', 'max:40'],
            'is_active' => ['nullable', 'boolean'],
            'rules' => ['required', 'array'],
        ]);

        $profile->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'version' => $validated['version'],
            'is_active' => (bool) ($validated['is_active'] ?? false),
            'rules_json' => $this->normaliseRules($validated['rules'], $profile->slug),
        ]);

        return back()->with('success', 'Style profile updated. New formatting runs will use it immediately.');
    }

    public function toggleStyleProfile(Request $request, JournalStyleProfile $profile)
    {
        $this->authorize('manageStyleProfiles', new \App\Models\Submission);

        $profile->update(['is_active' => ! $profile->is_active]);

        return back()->with('success', 'Profile '.($profile->is_active ? 'activated' : 'deactivated').'.');
    }

    /** Editorial workflow configuration. */
    public function workflow(Request $request)
    {
        $this->authorize('configureWorkflowSettings', new \App\Models\Submission);

        return view('admin.configuration.workflow', [
            'stages' => $this->workflow->activeStages(),
            'transitions' => $this->workflow->configuredTransitions(),
        ]);
    }

    public function updateStage(Request $request, EditorialStage $stage)
    {
        $this->authorize('configureWorkflowSettings', new \App\Models\Submission);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'description' => ['nullable', 'string', 'max:2000'],
            'default_deadline_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'required_permissions' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $stage->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'default_deadline_days' => $validated['default_deadline_days'] ?? null,
            'required_permissions' => $validated['required_permissions'] ?? [],
            'is_active' => (bool) ($validated['is_active'] ?? $stage->is_active),
        ]);

        return back()->with('success', 'Stage "'.$stage->name.'" updated.');
    }

    /** Retention policy. */
    public function retention(Request $request)
    {
        $this->authorize('manageRetention', new \App\Models\Submission);

        return view('admin.configuration.retention', [
            'policy' => DocumentRetentionPolicy::active(),
            'policies' => DocumentRetentionPolicy::orderBy('id')->get(),
        ]);
    }

    public function updateRetention(Request $request, DocumentRetentionPolicy $policy)
    {
        $this->authorize('manageRetention', new \App\Models\Submission);

        $validated = $request->validate([
            'retain_original_days' => ['nullable', 'integer', 'min:1', 'max:36500'],
            'retain_formatted_days' => ['nullable', 'integer', 'min:1', 'max:36500'],
            'retain_workspace_days' => ['nullable', 'integer', 'min:1', 'max:36500'],
            'retain_review_days' => ['nullable', 'integer', 'min:1', 'max:36500'],
            'retain_audit_days' => ['required', 'integer', 'min:365', 'max:36500'],
            'delete_formatted_on_publication' => ['nullable', 'boolean'],
            'anonymise_after_review' => ['nullable', 'boolean'],
        ]);

        // The original submission is the scholarly record of what was
        // submitted. A retention policy may not shorten its life below the
        // statutory minimum enforced here.
        $validated['retain_original_days'] = max(
            (int) ($validated['retain_original_days'] ?? 0),
            (int) ($policy->retain_original_days ?? 2555),
        );

        $policy->update([
            'retain_original_days' => $validated['retain_original_days'],
            'retain_formatted_days' => $validated['retain_formatted_days'] ?? null,
            'retain_workspace_days' => $validated['retain_workspace_days'] ?? null,
            'retain_review_days' => $validated['retain_review_days'] ?? null,
            'retain_audit_days' => $validated['retain_audit_days'],
            'delete_formatted_on_publication' => (bool) ($validated['delete_formatted_on_publication'] ?? false),
            'anonymise_after_review' => (bool) ($validated['anonymise_after_review'] ?? true),
        ]);

        return back()->with('success', 'Retention policy updated.');
    }

    /**
     * Coerces the flat form inputs into the shape the rules engine expects,
     * rejecting anything that is not a positive number.
     *
     * @return array<string, mixed>
     */
    private function normaliseRules(array $input, string $slug): array
    {
        $rules = \App\Services\Documents\Formatting\StyleProfileRepository::builtIn($slug)->requirements;

        foreach (['title_max_words', 'abstract_max_words', 'abstract_min_words'] as $key) {
            if (isset($input[$key]) && is_numeric($input[$key])) {
                $rules[$key] = (int) $input[$key];
            }
        }

        foreach (['line_spacing_twips', 'body_first_line_indent_twips', 'reference_hanging_indent_twips'] as $key) {
            if (isset($input[$key]) && is_numeric($input[$key])) {
                $rules[$key] = (int) $input[$key];
            }
        }

        if (isset($input['paper_label'])) {
            $rules['paper']['label'] = (string) $input['paper_label'];
        }

        foreach (['paper_width', 'paper_height'] as $pair) {
            if (isset($input[$pair]) && is_numeric($input[$pair])) {
                $key = $pair === 'paper_width' ? 'width_twips' : 'height_twips';
                $rules['paper'][$key] = (int) $input[$pair];
            }
        }

        foreach (['require_abstract', 'author_details_on_separate_cover_page', 'blinded_review_copy'] as $flag) {
            if (array_key_exists($flag, $input)) {
                $rules[$flag] = (bool) $input[$flag];
            }
        }

        return $rules;
    }
}