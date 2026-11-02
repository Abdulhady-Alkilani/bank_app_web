<?php

namespace App\Mcp\Resources;

use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Attributes\Uri;

#[Name('investigation-guide')]
#[Title('Investigation guide')]
#[Uri('fraud://investigation-guide')]
#[MimeType('text/markdown')]
#[Description('Step-by-step guide for investigating account takeover, mule accounts and shared-device cases. دليل التحقيق.')]
class InvestigationGuideResource extends FraudResource
{
    protected function render(): string
    {
        return $this->staticContent('investigation-guide.md');
    }
}
