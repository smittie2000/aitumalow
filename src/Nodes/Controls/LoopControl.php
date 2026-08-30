<?php

namespace Aitumalow\Nodes\Controls;

use Aitumalow\Attributes\WorkflowNode;
use Aitumalow\Contracts\NodeInterface;
use Aitumalow\DTOs\NodeInput;
use Aitumalow\DTOs\NodeOutput;
use Aitumalow\Enums\NodeType;
use Aitumalow\Nodes\HasDocumentation;

#[WorkflowNode(key: 'core.loop', name: 'Loop', category: 'Controls', type: NodeType::Control)]
class LoopControl implements NodeInterface
{
    use HasDocumentation;

    public function inputPorts(): array
    {
        return ['main'];
    }

    public function outputPorts(): array
    {
        return ['loop_item', 'loop_done'];
    }

    public static function configSchema(): array
    {
        return [
            ['key' => 'source_field', 'type' => 'string', 'label' => 'Array field to iterate (e.g. items, orders)', 'required' => true, 'supports_expression' => true],
        ];
    }

    public static function outputSchema(): array
    {
        return [
            'loop_item' => [
                ['key' => '_loop_index', 'type' => 'integer', 'label' => 'Loop Index'],
                ['key' => '_loop_parent', 'type' => 'object', 'label' => 'Parent Item'],
                ['key' => '_loop_item', 'type' => 'object', 'label' => 'Current Loop Item'],
            ],
        ];
    }

    /** @param array<string, mixed> $config */
    public function execute(NodeInput $input, array $config): NodeOutput
    {
        $loopItems = [];

        foreach ($input->items as $item) {
            $array = data_get($item, $config['source_field'], []);

            if (! is_array($array)) {
                $array = [$array];
            }

            foreach ($array as $index => $element) {
                $loopItems[] = [
                    '_loop_index' => $index,
                    '_loop_parent' => $item,
                    '_loop_item' => is_array($element) ? $element : ['value' => $element],
                ];
            }
        }

        return NodeOutput::ports([
            'loop_item' => $loopItems,
            'loop_done' => $input->items,
        ]);
    }
}
