<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use App\Models\TeamMember;
use Illuminate\Http\Response;

class TeamMemberController extends Controller
{
    // List team members for the authenticated enterprise owner
    public function index(Request $request)
    {
        $owner = $request->user();
        $members = TeamMember::where('user_id', $owner->id)
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));
        return response()->json($members);
    }

    // Create a new team member under the authenticated enterprise owner
    public function store(Request $request)
    {
        $owner = $request->user();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('team_members', 'email')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in(['admin', 'manager', 'editor', 'viewer'])],
            'permissions' => ['nullable', 'array'],
        ]);

        $member = TeamMember::create([
            'user_id' => $owner->id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'permissions' => $validated['permissions'] ?? null,
            'status' => 'active',
        ]);

        return response()->json([
            'message' => 'Team member created successfully',
            'member' => $member
        ], Response::HTTP_CREATED);
    }

    // Update a team member
    public function update(Request $request, TeamMember $member)
    {
        $owner = $request->user();
        abort_unless($member->user_id === $owner->id, 403, 'Unauthorized');

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('team_members', 'email')->ignore($member->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'role' => ['sometimes', Rule::in(['admin', 'manager', 'editor', 'viewer'])],
            'permissions' => ['nullable', 'array'],
            'status' => ['sometimes', Rule::in(['active', 'invited', 'suspended'])]
        ]);

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $member->update($validated);

        return response()->json([
            'message' => 'Team member updated successfully',
            'member' => $member
        ]);
    }

    // Delete a team member
    public function destroy(Request $request, TeamMember $member)
    {
        $owner = $request->user();
        abort_unless($member->user_id === $owner->id, 403, 'Unauthorized');
        $member->delete();
        return response()->json(['message' => 'Team member removed']);
    }

    // Team member login (separate from user login)
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string']
        ]);

        $member = TeamMember::where('email', $validated['email'])->first();
        if (!$member || !Hash::check($validated['password'], $member->password)) {
            return response()->json(['message' => 'Invalid credentials'], 422);
        }

        if ($member->status !== 'active') {
            return response()->json(['message' => 'Account is not active'], 403);
        }

        $member->forceFill(['last_login_at' => now()])->save();

        $tokenResult = $member->createToken('team_member_token');
        $token = $tokenResult->plainTextToken;

        // Expose owner plan as effective permission info
        $owner = $member->owner;

        return response()->json([
            'message' => 'Login successful',
            'user_type' => 'team_member',
            'member' => $member,
            'owner' => [
                'id' => $owner?->id,
                'name' => $owner?->name,
                'email' => $owner?->email,
                'membership_plan' => $owner?->membershipPlan?->name,
            ],
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_at' => now()->addMinutes((int) config('sanctum.expiration', 1440))->toISOString(),
            'expires_in' => (int) config('sanctum.expiration', 1440) * 60,
            'effective_permissions' => $member->effective_permissions,
            'redirect_to' => '/enterprise/dashboard'
        ]);
    }
}
