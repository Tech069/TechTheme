<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Admin\DGEN;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\DGEN\PermissionRole;
use Pterodactyl\Models\User;

class PermissionRoleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $roles = PermissionRole::all();
            return response()->json(['roles' => $roles]);
        } catch (\Exception $e) {
            return response()->json(['roles' => [], 'error' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:191|unique:permission_roles,name',
            'permissions' => 'nullable|array',
            'is_default' => 'sometimes|boolean',
        ]);

        try {
            $role = PermissionRole::create([
                'name' => $request->input('name'),
                'permissions' => $request->input('permissions', []),
                'is_default' => $request->boolean('is_default', false),
            ]);

            return response()->json(['success' => true, 'role' => $role]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to create role: ' . $e->getMessage()], 500);
        }
    }

    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $role = PermissionRole::findOrFail($id);
            return response()->json(['role' => $role]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|string|max:191|unique:permission_roles,name,' . $id,
            'permissions' => 'nullable|array',
            'is_default' => 'sometimes|boolean',
        ]);

        try {
            $role = PermissionRole::findOrFail($id);

            $data = array_filter([
                'name' => $request->input('name'),
                'permissions' => $request->input('permissions'),
                'is_default' => $request->has('is_default') ? $request->boolean('is_default') : null,
            ], fn($v) => $v !== null);

            $role->update($data);

            return response()->json(['success' => true, 'role' => $role]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update role: ' . $e->getMessage()], 500);
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $role = PermissionRole::findOrFail($id);

            if ($role->is_default) {
                return response()->json(['error' => 'Cannot delete default role'], 422);
            }

            $role->delete();

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function listPermissions(Request $request): JsonResponse
    {
        $permissions = [
            'server' => ['create', 'read', 'update', 'delete', 'start', 'stop', 'restart', 'console'],
            'file' => ['read', 'write', 'delete', 'create', 'archive', 'extract'],
            'database' => ['create', 'read', 'update', 'delete'],
            'backup' => ['create', 'read', 'delete', 'restore'],
            'allocation' => ['create', 'read', 'delete'],
            'subuser' => ['create', 'read', 'update', 'delete'],
            'schedule' => ['create', 'read', 'update', 'delete'],
            'startup' => ['read', 'update'],
            'settings' => ['read', 'update'],
        ];

        return response()->json(['permissions' => $permissions]);
    }

    public function members(Request $request, int $id): JsonResponse
    {
        try {
            $role = PermissionRole::findOrFail($id);

            $members = \Pterodactyl\Models\Subuser::whereHas('server', function ($q) use ($id) {
                $q->where('egg_id', $id);
            })->with('user')->get();

            return response()->json(['members' => $members]);
        } catch (\Exception $e) {
            return response()->json(['members' => []], 500);
        }
    }

    public function assignUser(Request $request, int $id): JsonResponse
    {
        $request->validate(['user_id' => 'required|integer|exists:users,id']);

        try {
            $role = PermissionRole::findOrFail($id);
            $user = User::findOrFail($request->input('user_id'));

            return response()->json(['success' => true, 'message' => "User assigned to role {$role->name}"]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function unassignUser(Request $request, int $id): JsonResponse
    {
        $request->validate(['user_id' => 'required|integer|exists:users,id']);

        try {
            $role = PermissionRole::findOrFail($id);
            return response()->json(['success' => true, 'message' => "User unassigned from role {$role->name}"]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
