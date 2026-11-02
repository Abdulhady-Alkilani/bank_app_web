<?php

// MCP server registrations (laravel/mcp auto-loads this file). Owner: Agent F.

use App\Http\Middleware\EnsureMcpAccess;
use App\Mcp\Servers\FraudInvestigationServer;
use Laravel\Mcp\Facades\Mcp;

// HTTP: Authorization: Bearer <Sanctum token with mcp:read (and optionally mcp:propose)>.
Mcp::web('/mcp/fraud', FraudInvestigationServer::class)
    ->middleware(['auth:sanctum', EnsureMcpAccess::class, 'throttle:120,1']);

// Local stdio (php artisan mcp:start fraud); set MCP_LOCAL_USER_EMAIL to the staff user the session acts as.
Mcp::local('fraud', FraudInvestigationServer::class);
