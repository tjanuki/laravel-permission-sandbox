<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class TeamsPermission
{
    public function handle(Request $request, Closure $next)
    {
        if (auth()->check()) {
            setPermissionsTeamId(session('team_id'));
        }

        return $next($request);
    }
}
