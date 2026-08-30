<?php

namespace Aitumalow\Nodes;

use Aitumalow\Contracts\WorkflowAction;
use Aitumalow\DTOs\NodeInput;
use Aitumalow\DTOs\NodeOutput;
use Aitumalow\DTOs\WorkflowContext;

final class WorkflowActionNode extends BaseNode
{
    public function __construct(
        private readonly WorkflowAction $action,
    ) {}

    public function execute(NodeInput $input, array $config): NodeOutput
    {
        $items = $input->items ?: [[]];

        return NodeOutput::main(array_map(
            fn (array $item): array => $this->action->handle(new WorkflowContext(
                input: $item,
                config: $config,
                items: $input->items,
                execution: $input->context,
            )),
            $items,
        ));
    }
}
