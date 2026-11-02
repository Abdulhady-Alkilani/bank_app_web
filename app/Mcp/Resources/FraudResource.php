<?php

namespace App\Mcp\Resources;

use App\Mcp\Support\McpAccess;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Resource;

/**
 * Base resource: checks mcp:read access and writes an audit entry for every read.
 */
abstract class FraudResource extends Resource
{
    abstract protected function render(): string;

    public function handle(Request $request): Response
    {
        $user = McpAccess::user($request);

        if (! $user || ! McpAccess::allows($user, McpAccess::READ)) {
            McpAccess::audit('mcp.resource_read', $user, $this->name(), [], 'denied');

            return Response::error(McpAccess::bilingual('Not authorised to use the fraud MCP server.', 'غير مصرح باستخدام خادم MCP لكشف الاحتيال.'));
        }

        $content = $this->render();
        McpAccess::audit('mcp.resource_read', $user, $this->name(), [], 'ok', ['uri' => $this->uri()]);

        return Response::text($content);
    }

    protected function staticContent(string $file): string
    {
        return (string) file_get_contents(resource_path('mcp/'.$file));
    }
}
