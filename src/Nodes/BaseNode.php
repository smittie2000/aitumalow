<?php

namespace Aitumalow\Nodes;

use Aitumalow\Contracts\NodeInterface;

abstract class BaseNode implements NodeInterface
{
    use HasDocumentation;

    public function inputPorts(): array
    {
        return ['main'];
    }

    public function outputPorts(): array
    {
        return ['main', 'error'];
    }

    public static function configSchema(): array
    {
        return [];
    }

    public static function outputSchema(): array
    {
        return [];
    }
}
