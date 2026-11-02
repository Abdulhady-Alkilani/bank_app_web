<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\McpAccess;
use App\Mcp\Support\McpToolException;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

/**
 * Base class of every fraud-investigation tool: authorises the caller (token ability + permission),
 * turns failures into error results, and writes a masked audit entry for EVERY call.
 */
abstract class FraudTool extends Tool
{
    /** Token ability required on top of mcp:read (proposal tools override this). */
    protected string $ability = McpAccess::READ;

    /**
     * @return array<string, mixed>
     */
    abstract protected function execute(Request $request, User $user): array;

    public function handle(Request $request): Response|ResponseFactory
    {
        $user = McpAccess::user($request);
        $arguments = $request->all();

        if (! $user || ! McpAccess::allows($user, McpAccess::READ)) {
            McpAccess::audit('mcp.tool_called', $user, $this->name(), $arguments, 'denied');

            return Response::error(McpAccess::bilingual('Not authorised to use the fraud MCP server.', 'غير مصرح باستخدام خادم MCP لكشف الاحتيال.'));
        }

        if ($this->ability !== McpAccess::READ && ! McpAccess::allows($user, $this->ability)) {
            McpAccess::audit('mcp.tool_called', $user, $this->name(), $arguments, 'denied');

            return Response::error(McpAccess::bilingual(
                "This tool requires a token with the {$this->ability} ability.",
                "تتطلب هذه الأداة رمزاً يملك الصلاحية {$this->ability}.",
            ));
        }

        try {
            $result = $this->execute($request, $user);
        } catch (ValidationException $e) {
            McpAccess::audit('mcp.tool_called', $user, $this->name(), $arguments, 'invalid');

            return Response::error(McpAccess::bilingual('Invalid input: ', 'مدخلات غير صالحة: ').implode(' ', $e->validator->errors()->all()));
        } catch (McpToolException $e) {
            McpAccess::audit('mcp.tool_called', $user, $this->name(), $arguments, $e->auditStatus);

            return Response::error($e->getMessage());
        }

        McpAccess::audit('mcp.tool_called', $user, $this->name(), $arguments, 'ok');

        return Response::structured($result);
    }
}
