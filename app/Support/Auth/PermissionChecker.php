<?php

namespace App\Support\Auth;

use App\Enums\UserRole;
use Illuminate\Support\Facades\DB;

class PermissionChecker
{
    private static array $cache = [];

    /**
     * Check if user has a specific permission on a module.
     */
    public static function can(int $userId, string $module, string $action): bool
    {
        $user = DB::table('users')->where('id', $userId)->first(['role']);
        if (! $user) {
            return false;
        }

        $role = UserRole::tryFrom($user->role ?? 'operator') ?? UserRole::Operator;

        // CEO and Admin bypass permissions
        if ($role->atLeast(UserRole::Admin)) {
            return true;
        }

        $perms = self::loadPermissions($userId);
        $modulePerm = $perms[$module] ?? null;

        if (! $modulePerm) {
            return false;
        }

        return match ($action) {
            'view' => (bool) $modulePerm->can_view,
            'create' => (bool) $modulePerm->can_create,
            'edit' => (bool) $modulePerm->can_edit,
            'delete' => (bool) $modulePerm->can_delete,
            default => false,
        };
    }

    /**
     * Check if user can view a module (for sidebar visibility).
     */
    public static function canView(int $userId, string $module): bool
    {
        return self::can($userId, $module, 'view');
    }

    /**
     * Load all permissions for a user (cached per request).
     */
    private static function loadPermissions(int $userId): array
    {
        if (isset(self::$cache[$userId])) {
            return self::$cache[$userId];
        }

        $perms = DB::table('user_permissions')
            ->where('user_id', $userId)
            ->get()
            ->keyBy('module')
            ->toArray();

        self::$cache[$userId] = $perms;

        return $perms;
    }
}
