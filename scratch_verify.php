<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;

DB::beginTransaction();

try {
    // 1. Ensure test roles exist in the Spatie roles table
    $spatieRoles = ['Super Admin', 'Admin', 'Managing Editor', 'Editor', 'Reviewer', 'Author'];
    foreach ($spatieRoles as $rName) {
        Role::firstOrCreate(['name' => $rName, 'guard_name' => 'web']);
    }

    // 2. Helper to create a user with Spatie and legacy roles
    $createUser = function($email, $legacyRole, $spatieRole = null) {
        $user = User::create([
            'name' => "Test $email",
            'email' => $email,
            'password' => bcrypt('password'),
        ]);
        if ($legacyRole) {
            $user->syncLegacyRoleColumn($legacyRole);
        }
        if ($spatieRole) {
            $user->assignRole($spatieRole);
        }
        $user->refresh();
        return $user;
    };

    // Create different mock users
    $uSuperSpatie = $createUser('super-spatie@test.com', 'Super Admin', 'Super Admin');
    $uAdminSpatie = $createUser('admin-spatie@test.com', 'Admin', 'Admin');
    $uEditorSpatie = $createUser('editor-spatie@test.com', 'Editor', 'Editor');
    
    $uSuperLegacyOnly = $createUser('super-legacy@test.com', 'Super Admin', null);
    $uAdminLegacyOnly = $createUser('admin-legacy@test.com', 'Admin', null);
    $uManagingEditorLegacyOnly = $createUser('managing-editor-legacy@test.com', 'Managing Editor', null);
    $uManagingAdminLegacyOnly = $createUser('managing-admin-legacy@test.com', 'Managing Admin', null);
    $uEditorLegacyOnly = $createUser('editor-legacy@test.com', 'Editor', null);
    $uReviewerLegacyOnly = $createUser('reviewer-legacy@test.com', 'Reviewer', null);
    $uAuthorLegacyOnly = $createUser('author-legacy@test.com', 'Author', null);

    echo "--- Testing hasRole() function overrides ---\n";

    // Test helper assertion
    $assertRole = function($user, $checkRole, $expected) {
        $result = $user->hasRole($checkRole);
        $status = ($result === $expected) ? "PASS" : "FAIL (Expected " . ($expected ? 'true' : 'false') . ", got " . ($result ? 'true' : 'false') . ")";
        echo "User '{$user->email}' (db_role: '{$user->role}') hasRole('{$checkRole}'): $status\n";
    };

    // Check Spatie role checks
    $assertRole($uSuperSpatie, 'Super Admin', true);
    $assertRole($uAdminSpatie, 'Admin', true);
    $assertRole($uEditorSpatie, 'Editor', true);

    // Check legacy role checks (no Spatie roles)
    $assertRole($uSuperLegacyOnly, 'Super Admin', true);
    $assertRole($uAdminLegacyOnly, 'Admin', true);
    $assertRole($uEditorLegacyOnly, 'Editor', true);
    $assertRole($uReviewerLegacyOnly, 'Reviewer', true);
    $assertRole($uAuthorLegacyOnly, 'Author', true);

    // Check equivalence of Managing Admin <=> Managing Editor
    $assertRole($uManagingEditorLegacyOnly, 'Managing Editor', true);
    $assertRole($uManagingEditorLegacyOnly, 'Managing Admin', true);
    $assertRole($uManagingAdminLegacyOnly, 'Managing Editor', true);
    $assertRole($uManagingAdminLegacyOnly, 'Managing Admin', true);

    // Negative tests
    $assertRole($uSuperLegacyOnly, 'Editor', false);
    $assertRole($uEditorLegacyOnly, 'Super Admin', false);

    echo "\n--- Testing User::role() scope query overrides ---\n";

    $assertScopeCount = function($rolesToCheck, $expectedEmails) {
        $foundEmails = User::role($rolesToCheck)->pluck('email')->toArray();
        $missing = array_diff($expectedEmails, $foundEmails);
        $extra = array_diff($foundEmails, $expectedEmails);
        if (empty($missing) && empty($extra)) {
            echo "Scope check for " . json_encode($rolesToCheck) . ": PASS\n";
        } else {
            echo "Scope check for " . json_encode($rolesToCheck) . ": FAIL (Missing: " . json_encode($missing) . ", Extra: " . json_encode($extra) . ")\n";
        }
    };

    // Check scope returns users matching Super Admin
    $assertScopeCount('Super Admin', ['super-spatie@test.com', 'super-legacy@test.com']);

    // Check scope returns users matching Admin
    $assertScopeCount('Admin', ['admin-spatie@test.com', 'admin-legacy@test.com']);

    // Check scope returns Managing Editor (should include Managing Editor and Managing Admin)
    $assertScopeCount('Managing Editor', ['managing-editor-legacy@test.com', 'managing-admin-legacy@test.com']);
    $assertScopeCount('Managing Admin', ['managing-editor-legacy@test.com', 'managing-admin-legacy@test.com']);

    // Check array lookup
    $assertScopeCount(['Super Admin', 'Managing Editor'], [
        'super-spatie@test.com',
        'super-legacy@test.com',
        'managing-editor-legacy@test.com',
        'managing-admin-legacy@test.com'
    ]);

    echo "\nVerification script completed successfully.\n";

} catch (\Exception $e) {
    echo "ERROR during verification: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
} finally {
    // Rollback changes to keep DB completely clean
    DB::rollBack();
    echo "Database changes rolled back.\n";
}
