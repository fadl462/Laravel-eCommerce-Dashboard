<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function __construct(protected ActivityLogger $activityLogger)
    {
    }

    public function index()
    {
        $roles = Role::with('permissions')->get();

        return response()->json($roles->map(fn (Role $r) => [
            'id' => $r->id,
            'name' => $r->name,
            'display_name' => $r->display_name,
            'is_system' => $r->is_system,
            'permissions' => $r->permissions->pluck('key'),
        ]));
    }

    public function permissions()
    {
        // Grouped exactly as the dashboard's permission matrix renders them.
        return response()->json(
            Permission::all()->groupBy('group')->map(fn ($group) => $group->pluck('description', 'key'))
        );
    }

    /** Full replace of a role's permission set — this is what the matrix checkboxes call. */
    public function syncPermissions(Request $request, Role $role)
    {
        abort_unless($request->user()->hasPermission('administrators.manage'), 403);
        abort_if($role->name === 'super_admin', 422, 'Super Administrator permissions cannot be modified.');

        $data = $request->validate([
            'permission_keys' => ['required', 'array'],
            'permission_keys.*' => ['string', 'exists:permissions,key'],
        ]);

        $ids = Permission::whereIn('key', $data['permission_keys'])->pluck('id');
        $role->permissions()->sync($ids);

        $this->activityLogger->log($request->user(), 'Role changed', 'Administrators', $role, $role->display_name, [
            'permissions' => $data['permission_keys'],
        ]);

        return response()->json(['message' => 'Permissions updated.']);
    }
}
