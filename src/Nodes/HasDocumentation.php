<?php

namespace Aitumalow\Nodes;

use Aitumalow\Attributes\WorkflowNode;
use Aitumalow\Enums\NodeType;
use ReflectionClass;

trait HasDocumentation
{
    public static function documentation(): ?string
    {
        $ref = new ReflectionClass(static::class);
        $attrs = $ref->getAttributes(WorkflowNode::class);

        if (! $attrs) {
            return null;
        }

        $attr = $attrs[0]->newInstance();
        $segments = explode('.', $attr->key);
        $filename = str_replace('_', '-', $segments[array_key_last($segments)]).'.md';
        $folder = $attr->type === NodeType::Trigger ? 'triggers' : 'nodes';
        $path = dirname(__DIR__, 2)."/docs/{$folder}/{$filename}";

        if (! file_exists($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        return $contents === false ? null : $contents;
    }
}
