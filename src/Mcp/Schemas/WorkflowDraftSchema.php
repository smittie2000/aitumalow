<?php

declare(strict_types=1);

namespace Aitumalow\Mcp\Schemas;

use Illuminate\Contracts\JsonSchema\JsonSchema;

final class WorkflowDraftSchema
{
    /** @return array<string, mixed> */
    public static function fields(JsonSchema $schema, bool $required = true): array
    {
        $nodes = $schema->array()->items($schema->object([
            'id' => $schema->string()->required()->description('Document-local lowercase alias used by edges.'),
            'capability' => $schema->string()->required()->description('Exact stable key from the workflow catalog.'),
            'name' => $schema->string()->description('Optional instance name.'),
            'config' => $schema->object()->description('Configuration matching the registered capability schema.'),
        ]))->description('Complete node list. No PHP classes or executable code.');
        $edges = $schema->array()->items($schema->object([
            'from' => $schema->string()->required()->description('Source node alias.'),
            'to' => $schema->string()->required()->description('Target node alias.'),
            'output' => $schema->string()->default('main')->description('Registered source output port.'),
            'input' => $schema->string()->default('main')->description('Registered target input port.'),
        ]))->description('Complete connection list.');

        return ['nodes' => $required ? $nodes->required() : $nodes, 'edges' => $required ? $edges->required() : $edges];
    }
}
