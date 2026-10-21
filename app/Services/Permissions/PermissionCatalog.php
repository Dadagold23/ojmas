<?php

namespace App\Services\Permissions;

/**
 * The catalogue of journal capabilities.
 *
 * Design rules
 * ------------
 *  - **Additive.** Roles are never renamed or removed. Permissions are attached
 *    to the six roles that already exist, so no existing account loses access.
 *  - **The legacy `users.role` shim is untouched.** `User::hasRole()` and
 *    `User::scopeRole()` keep working exactly as before.
 *  - **Managing Editor is explicitly bounded.** It receives workflow powers
 *    but never the unrestricted Super Admin capabilities: no user management,
 *    no payment administration, no system configuration, no role assignment.
 *  - Every ability name is `<domain>.<action>` so the admin screens can group
 *    them without hardcoding strings.
 */
final class PermissionCatalog
{
    /**
     * Domain label shown in the administration screens.
     */
    public const DOMAINS = [
        'submission' => 'Submissions',
        'review' => 'Peer review',
        'decision' => 'Editorial decisions',
        'workflow' => 'Workflow management',
        'manuscript' => 'Manuscript documents',
        'comment' => 'Comments and discussion',
        'payment' => 'Payments and fees',
        'publication' => 'Publication and production',
        'advertising' => 'Advertisements and announcements',
        'administration' => 'Administration',
        'audit' => 'Audit and reporting',
    ];

    /**
     * The full permission list, grouped by domain.
     *
     * @return array<string, array<int, string>>
     */
    public static function all(): array
    {
        return [
            'submission' => [
                'submission.view.own',
                'submission.view.any',
                'submission.create',
                'submission.edit.own',
                'submission.delete.own',
                'submission.upload.revision',
                'submission.respond.revision',
            ],
            'review' => [
                'review.view.assigned',
                'review.view.any',
                'review.draft',
                'review.submit',
                'review.view.confidential',
                'review.assign',
                'review.invite',
                'review.remind',
            ],
            'decision' => [
                'decision.view',
                'decision.record',
                'decision.communicate',
            ],
            'workflow' => [
                'workflow.view',
                'workflow.manage',
                'workflow.transition',
                'workflow.manage.rounds',
                'workflow.assign.editor',
                'workflow.deadlines',
                'workflow.view.reports',
            ],
            'manuscript' => [
                'manuscript.view',
                'manuscript.edit',
                'manuscript.download',
                'manuscript.export',
                'manuscript.restore.version',
                'manuscript.compare',
                'manuscript.view.anonymised',
            ],
            'comment' => [
                'comment.create',
                'comment.reply',
                'comment.resolve',
                'comment.view.author',
                'comment.view.editorial',
                'comment.view.confidential',
            ],
            'payment' => [
                'payment.view.own',
                'payment.view.any',
                'payment.record',
                'payment.refund',
                'payment.configure',
            ],
            'publication' => [
                'publication.view',
                'publication.publish',
                'publication.produce',
                'publication.manage.issues',
            ],
            'advertising' => [
                'advertising.view',
                'advertising.manage',
                'advertising.reports',
                'advertising.configure',
            ],
            'administration' => [
                'user.manage',
                'role.manage',
                'settings.manage',
                'settings.style.profiles',
                'settings.workflow',
                'settings.documents',
                'settings.retention',
                'journal.manage',
            ],
            'audit' => [
                'audit.view',
                'audit.documents',
                'audit.reports',
            ],
        ];
    }

    /**
     * @return array<int, string> Every permission name.
     */
    public static function names(): array
    {
        return array_merge(...array_values(self::all()));
    }

    /**
     * Which roles receive which permissions.
     *
     * Note the deliberate omissions:
     *  - `Managing Editor` has no `user.manage`, `payment.refund`,
     *    `settings.manage`, `role.manage`, `decision.record` or
     *    `publication.publish`. It coordinates the workflow; it does not hold
     *    the keys to the building.
     *  - `Reviewer` cannot see another reviewer's report.
     *  - `Author` cannot see confidential material or the audit trail.
     *
     * @return array<string, array<int, string>>
     */
    public static function roleMatrix(): array
    {
        return [
            'Author' => [
                'submission.view.own',
                'submission.create',
                'submission.edit.own',
                'submission.delete.own',
                'submission.upload.revision',
                'submission.respond.revision',
                'manuscript.view',
                'manuscript.edit',
                'manuscript.download',
                'manuscript.compare',
                'comment.create',
                'comment.reply',
                'comment.view.author',
                'payment.view.own',
            ],

            'Reviewer' => [
                'submission.view.own',
                'review.view.assigned',
                'review.draft',
                'review.submit',
                'review.view.confidential',
                'manuscript.view',
                'manuscript.download',
                'comment.create',
                'comment.reply',
                'comment.view.editorial',
                'comment.view.confidential',
            ],

            'Editor' => [
                'submission.view.own',
                'submission.view.any',
                'review.view.assigned',
                'review.view.any',
                'review.assign',
                'review.invite',
                'decision.view',
                'decision.record',
                'decision.communicate',
                'workflow.view',
                'workflow.transition',
                'manuscript.view',
                'manuscript.edit',
                'manuscript.download',
                'manuscript.export',
                'manuscript.compare',
                'manuscript.restore.version',
                'comment.create',
                'comment.reply',
                'comment.resolve',
                'comment.view.author',
                'comment.view.editorial',
                'comment.view.confidential',
                'publication.view',
                'publication.manage.issues',
                'audit.view',
                'audit.documents',
            ],

            'Managing Editor' => [
                'submission.view.own',
                'submission.view.any',
                'review.view.any',
                'review.assign',
                'review.invite',
                'review.remind',
                'decision.view',
                'decision.communicate',
                'workflow.view',
                'workflow.manage',
                'workflow.transition',
                'workflow.manage.rounds',
                'workflow.assign.editor',
                'workflow.deadlines',
                'workflow.view.reports',
                'manuscript.view',
                'manuscript.edit',
                'manuscript.download',
                'manuscript.export',
                'manuscript.compare',
                'manuscript.restore.version',
                'manuscript.view.anonymised',
                'comment.create',
                'comment.reply',
                'comment.resolve',
                'comment.view.author',
                'comment.view.editorial',
                'comment.view.confidential',
                'publication.view',
                'publication.manage.issues',
                'audit.view',
                'audit.documents',
                'audit.reports',
            ],

            'Admin' => [
                'submission.view.own',
                'submission.view.any',
                'review.view.any',
                'review.assign',
                'review.invite',
                'review.remind',
                'decision.view',
                'decision.record',
                'decision.communicate',
                'workflow.view',
                'workflow.manage',
                'workflow.transition',
                'workflow.manage.rounds',
                'workflow.assign.editor',
                'workflow.deadlines',
                'workflow.view.reports',
                'manuscript.view',
                'manuscript.edit',
                'manuscript.download',
                'manuscript.export',
                'manuscript.compare',
                'manuscript.restore.version',
                'manuscript.view.anonymised',
                'comment.create',
                'comment.reply',
                'comment.resolve',
                'comment.view.author',
                'comment.view.editorial',
                'comment.view.confidential',
                'payment.view.any',
                'payment.record',
                'payment.refund',
                'payment.configure',
                'publication.view',
                'publication.publish',
                'publication.produce',
                'publication.manage.issues',
                'user.manage',
                'journal.manage',
                'advertising.view',
                'advertising.manage',
                'advertising.reports',
                'audit.view',
                'audit.documents',
                'audit.reports',
            ],

            'Super Admin' => self::names(),
        ];
    }

    /**
     * Permissions the Managing Editor must never hold, whatever else changes.
     *
     * Used by tests and by the administration screen to show an explicit
     * boundary rather than relying on absence.
     *
     * @return array<int, string>
     */
    public static function managingEditorProhibitions(): array
    {
        return [
            'user.manage',
            'role.manage',
            'settings.manage',
            'settings.style.profiles',
            'settings.workflow',
            'settings.documents',
            'settings.retention',
            'payment.record',
            'payment.refund',
            'payment.configure',
            'decision.record',
            'publication.publish',
            'publication.produce',
            'submission.delete.own',
        ];
    }

    /**
     * @return array<int, string> All known role names.
     */
    public static function roles(): array
    {
        return array_keys(self::roleMatrix());
    }

    /**
     * Group permissions for display.
     *
     * @return array<string, array{label: string, permissions: array<int, string>}>
     */
    public static function grouped(): array
    {
        $grouped = [];

        foreach (self::all() as $domain => $permissions) {
            $grouped[$domain] = [
                'label' => self::DOMAINS[$domain] ?? $domain,
                'permissions' => $permissions,
            ];
        }

        return $grouped;
    }
}