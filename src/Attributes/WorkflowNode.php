<?php

namespace Aitumalow\Attributes;

use Aitumalow\Enums\NodeType;
use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class WorkflowNode
{
    public function __construct(
        public string $key,
        public string $name,
        public string $category,
        public string $icon = '',
        public string $description = '',
        public ?NodeType $type = null,
    ) {}
}
