<?php

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

// Create team and roles
Route::get('/setup-team', function () {
    // Create a team
    $team = Team::create(['name' => 'Marketing Team']);

    // Create global role (available across all teams)
    $adminRole = Role::create(['name' => 'admin', 'team_id' => null]);

    // Create team-specific role
    $editorRole = Role::create([
        'name' => 'editor',
        'team_id' => $team->id
    ]);

    return "Team and roles created successfully";
});

// Assign user to team and role
Route::get('/assign-team/{userId}/{teamId}', function ($userId, $teamId) {
    $user = User::findOrFail($userId);
    $team = Team::findOrFail($teamId);

    // Assign user to team
    $user->team_id = $team->id;
    $user->save();

    // Set permission context for this team
    setPermissionsTeamId($team->id);

    // Clear cached permissions
    $user->unsetRelation('roles')->unsetRelation('permissions');

    // Assign role for this team
    $user->assignRole('editor');

    return "User assigned to team and role";
});

// Switch between teams
Route::get('/switch-team/{teamId}', function ($teamId) {
    \Illuminate\Support\Facades\Auth::login(User::first());
    $team = Team::findOrFail($teamId);
    $user = auth()->user();

    // Verify user belongs to this team
    if ($user->team_id == $team->id) {
        // Store team_id in session
        session(['team_id' => $team->id]);

        // Set permission context
        setPermissionsTeamId($team->id);

        // Clear cached permissions
        $user->unsetRelation('roles')->unsetRelation('permissions');

        return "Switched to team: " . $team->name;
    }

    return "Access denied";
});

// Create initial permissions and roles
Route::get('/setup-permissions', function () {
    // Create permissions first
    Permission::create(['name' => 'edit-posts']);
    Permission::create(['name' => 'publish-posts']);
    Permission::create(['name' => 'delete-posts']);

    return "Permissions created successfully";
});

// Assign permissions to role
Route::get('/assign-permissions/{teamId}', function ($teamId) {
    $team = Team::findOrFail($teamId);

    // Set team context
    setPermissionsTeamId($team->id);

    // Get or create editor role for this team
    $editorRole = Role::firstOrCreate([
        'name' => 'editor',
        'team_id' => $team->id
    ]);

    // Assign permissions to role
    $editorRole->givePermissionTo([
        'edit-posts',
        'publish-posts'
    ]);

    return "Permissions assigned to editor role in team {$team->id}";
});

// Check permissions in current team context
Route::get('/check-permission', function () {
    $user = auth()->user();

    // Make sure team context is set
    if (!session('team_id')) {
        return "No team selected";
    }

    $hasEditorRole = $user->hasRole('editor');
    $canEditPosts = $user->hasPermissionTo('edit-posts');

    return [
        'team_id' => session('team_id'),
        'is_editor' => $hasEditorRole,
        'can_edit_posts' => $canEditPosts
    ];
})->middleware('auth');

Route::get('/list-permissions', function () {
    return [
        'all_permissions' => Permission::all()->pluck('name'),
        'all_roles' => Role::all()->pluck('name', 'id')
    ];
});

// Create and assign permissions
Route::get('/setup-permissions', function () {
    // Create permissions
    $editPosts = Permission::create(['name' => 'edit-posts']);
    $publishPosts = Permission::create(['name' => 'publish-posts']);

    // Get team-specific editor role
    $team = Team::first();
    setPermissionsTeamId($team->id);

    $editorRole = Role::where('name', 'editor')
        ->where('team_id', $team->id)
        ->first();

    // Assign permissions to role
    $editorRole->givePermissionTo(['edit-posts', 'publish-posts']);

    return "Permissions set up successfully";
});

// List user's roles and permissions in current team
Route::get('/my-permissions', function () {
    $user = auth()->user();
    $teamId = session('team_id');

    if (!$teamId) {
        return "No team selected";
    }

    setPermissionsTeamId($teamId);
    $user->unsetRelation('roles')->unsetRelation('permissions');

    return [
        'team' => Team::find($teamId)->name,
        'roles' => $user->getRoleNames(),
        'permissions' => $user->getAllPermissions()->pluck('name')
    ];
})->middleware('auth');
