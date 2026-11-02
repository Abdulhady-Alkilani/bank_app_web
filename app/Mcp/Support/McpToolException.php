<?php

namespace App\Mcp\Support;

use RuntimeException;

/**
 * A handled, user-facing failure of an MCP primitive (returned to the model as an error result, never thrown to the client).
 */
class McpToolException extends RuntimeException
{
    public function __construct(string $message, public readonly string $auditStatus = 'error')
    {
        parent::__construct($message);
    }

    public static function denied(string $message): self
    {
        return new self($message, 'denied');
    }

    public static function notFound(string $message): self
    {
        return new self($message, 'not_found');
    }
}
