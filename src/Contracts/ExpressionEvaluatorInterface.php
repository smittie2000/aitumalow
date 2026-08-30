<?php

namespace Aitumalow\Contracts;

interface ExpressionEvaluatorInterface
{
    /**
     * Resolve a template string containing {{ expression }} blocks.
     *
     * @param  array<string, mixed>  $variables
     */
    public function resolve(string $template, array $variables): mixed;

    /**
     * Recursively resolve all expression strings within a config array.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     */
    public function resolveConfig(array $config, array $variables): array;
}
