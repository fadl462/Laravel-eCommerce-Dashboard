<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdministratorController extends Controller
{
    public function __construct(protected ActivityLogger $activityLogger)
    {
    }

    public function index()
    {
        $admins = User::with('role')->orderBy('name')->get();

        return response()->json($admins->map(fn (User $u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'role' => $u->role?->display_name,
            'status' => $u->status,
            'last_login_at' => $u->last_login_at?->toIso8601String(),
        ]));
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->hasPermission('administrators.manage'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'role_id' => ['required', 'exists:roles,id'],
        ]);

        // Temporary password — a real deployment would email an invite/reset
        // link here instead of returning the password in the response.
        $tempPassword = Str::random(12);

        $admin = User::create([
            ...$data,
            'password' => Hash::make($tempPassword),
            'status' => 'active',
        ]);

        $this->activityLogger->log($request->user(), 'Invited administrator', 'Administrators', $admin, $admin->name);

        return response()->json([
            'admin' => $admin->load('role'),
            'temporary_password' => $tempPassword,
        ], 201);
    }

    public function setStatus(Request $request, User $admin)
    {
        abort_unless($request->user()->hasPermission('administrators.manage'), 403);
        abort_if($admin->id === $request->user()->id, 422, 'You cannot suspend your own account.');

        $data = $request->validate(['status' => ['required', 'in:active,suspended']]);
        $admin->update($data);

        // Revoke active sessions immediately on suspension.
        if ($data['status'] === 'suspended') {
            $admin->tokens()->delete();
        }

        $this->activityLogger->log($request->user(), 'Administrator status changed', 'Administrators', $admin, $admin->name, $data);

        return response()->json(['message' => 'Status updated.']);
    }
}
