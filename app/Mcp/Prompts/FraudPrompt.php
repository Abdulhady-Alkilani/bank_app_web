<?php

namespace App\Mcp\Prompts;

use App\Mcp\Support\McpAccess;
use App\Mcp\Support\McpToolException;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Prompt;

/**
 * Base prompt: checks mcp:read access and the visibility of the referenced record, and audits every call.
 */
abstract class FraudPrompt extends Prompt
{
    abstract protected function render(Request $request, User $user): string;

    public function handle(Request $request): Response
    {
        $user = McpAccess::user($request);
        $arguments = $request->all();

        if (! $user || ! McpAccess::allows($user, McpAccess::READ)) {
            McpAccess::audit('mcp.prompt_requested', $user, $this->name(), $arguments, 'denied');

            return Response::error(McpAccess::bilingual('Not authorised to use the fraud MCP server.', 'غير مصرح باستخدام خادم MCP لكشف الاحتيال.'));
        }

        try {
            $text = $this->render($request, $user);
        } catch (ValidationException $e) {
            McpAccess::audit('mcp.prompt_requested', $user, $this->name(), $arguments, 'invalid');

            return Response::error(McpAccess::bilingual('Invalid input: ', 'مدخلات غير صالحة: ').implode(' ', $e->validator->errors()->all()));
        } catch (McpToolException $e) {
            McpAccess::audit('mcp.prompt_requested', $user, $this->name(), $arguments, $e->auditStatus);

            return Response::error($e->getMessage());
        }

        McpAccess::audit('mcp.prompt_requested', $user, $this->name(), $arguments, 'ok');

        return Response::text($text);
    }
}
