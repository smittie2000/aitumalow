<?php

namespace Aitumalow\Contracts;

use Aitumalow\DTOs\NodeInput;
use Aitumalow\DTOs\NodeOutput;

interface NodeInterface
{
    /**
     * Input port names this node accepts.
     * Trigger nodes return an empty array.
     *
     * @return string[]
     */
    public function inputPorts(): array;

    /**
     * Output port names this node can produce.
     *
     * @return string[]
     */
    public function outputPorts(): array;

    /**
     * Execute the node's logic.
     *
     * @param  array<string, mixed>  $config
     */
    public function execute(NodeInput $input, array $config): NodeOutput;

    /**
     * Config field definitions for UI form generation.
     *
     * Each entry: ['key' => string, 'type' => string, 'label' => string, 'required' => bool, ...]
     *
     * @return array<int, array<string, mixed>>
     */
    public static function configSchema(): array;

    /**
     * Output field definitions per port for variable discovery.
     *
     * Each entry: ['key' => string, 'type' => string, 'label' => string]
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public static function outputSchema(): array;
}
