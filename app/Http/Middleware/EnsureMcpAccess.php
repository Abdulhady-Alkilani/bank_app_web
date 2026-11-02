<?php

namespace App\Http\Middleware;

use App\Mcp\Support\McpAccess;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for the MCP endpoint: the Sanctum token must carry `mcp:read` and its owner must be an
 * active staff user holding the `mcp.use` permission.
 */
class EnsureMcpAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! McpAccess::allows($user, McpAccess::READ)) {
            McpAccess::audit('mcp.access_denied', $user instanceof User ? $user : null, 'mcp', [], 'denied');

            return response()->json([
                'jsonrpc' => '2.0',
                'id' => null,
                'error' => ['code' => -32001, 'message' => 'Forbidden: an active staff token with the mcp:read ability and the mcp.use permission is required.'],
            ], 403);
        }

        return $next($request);
    }
}
