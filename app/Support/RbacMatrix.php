<?php

namespace App\Support;

use App\Models\Permission;
use App\Models\Role;

/**
 * Single source of truth for roles and the permissions they hold (plan section 2).
 */
class RbacMatrix
{
    public const PERMISSIONS = [
        'alerts.view', 'alerts.decide', 'cases.manage', 'accounts.freeze', 'accounts.unfreeze', 'approvals.approve',
        'users.manage', 'roles.manage', 'settings.manage', 'rules.manage', 'lists.manage', 'audit.view', 'audit.export',
        'integrations.manage', 'models.view', 'models.promote', 'reports.view', 'assignments.manage', 'qa.review',
        'rule_suggestions.create', 'rule_suggestions.review', 'system.health', 'backups.manage', 'mcp.use', 'customer.portal',
    ];

    /**
     * @return array<string, array{name: string, label: string, permissions: array<int, string>}>
     */
    public static function roles(): array
    {
        $staffPermissions = array_values(array_diff(self::PERMISSIONS, ['customer.portal']));

        return [
            'admin' => [
                'name' => 'Administrator',
                'label' => 'مدير النظام',
                'permissions' => $staffPermissions,
            ],
            'supervisor' => [
                'name' => 'Supervisor',
                'label' => 'مشرف',
                'permissions' => [
                    'alerts.view', 'alerts.decide', 'cases.manage', 'accounts.freeze', 'accounts.unfreeze', 'approvals.approve',
                    'assignments.manage', 'qa.review', 'rule_suggestions.review', 'reports.view', 'audit.view', 'models.view', 'mcp.use',
                ],
            ],
            'analyst' => [
                'name' => 'Analyst',
                'label' => 'محلل',
                'permissions' => ['alerts.view', 'alerts.decide', 'cases.manage', 'accounts.freeze', 'rule_suggestions.create', 'mcp.use'],
            ],
            'customer' => [
                'name' => 'Customer',
                'label' => 'عميل',
                'permissions' => ['customer.portal'],
            ],
        ];
    }

    public static function seedPermissions(): void
    {
        foreach (self::PERMISSIONS as $name) {
            Permission::firstOrCreate(['name' => $name], ['description' => $name]);
        }
    }

    /**
     * Create (or fetch) a role with its permissions synced from the matrix.
     */
    public static function ensureRole(string $slug): Role
    {
        self::seedPermissions();

        $definition = self::roles()[$slug] ?? ['name' => ucfirst($slug), 'label' => ucfirst($slug), 'permissions' => []];

        $role = Role::firstOrCreate(
            ['slug' => $slug],
            ['role_name' => $definition['name'], 'label' => $definition['label']],
        );

        $role->permissions()->sync(
            Permission::whereIn('name', $definition['permissions'])->pluck('id')->all(),
        );

        return $role;
    }
}
