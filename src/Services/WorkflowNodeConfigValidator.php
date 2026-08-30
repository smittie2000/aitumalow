<?php

namespace Aitumalow\Services;

use Aitumalow\DTOs\ExecutionScope;
use Aitumalow\Registry\NodeRegistry;
use Aitumalow\Registry\ReferenceProviderRegistry;
use Cron\CronExpression;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final readonly class WorkflowNodeConfigValidator
{
    public function __construct(
        private NodeRegistry $registry,
        private Factory $validator,
        private ReferenceProviderRegistry $referenceProviders,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function validate(string $nodeKey, array $config, ?ExecutionScope $scope = null): array
    {
        $definition = $this->registry->definition($nodeKey);

        if ($definition === null) {
            throw ValidationException::withMessages([
                'key' => "Workflow node [{$nodeKey}] is not registered.",
            ]);
        }

        $schema = $definition['config_schema'] ?? [];
        $fields = [];

        if (is_array($schema)) {
            foreach ($schema as $field) {
                if (! is_array($field)
                    || ! isset($field['key'])
                    || ! is_string($field['key'])
                    || in_array($field['type'] ?? null, ['info', 'section'], true)) {
                    continue;
                }

                $fields[$field['key']] = $field;
            }
        }

        $unknown = array_diff(array_keys($config), array_keys($fields));

        if ($unknown !== []) {
            throw ValidationException::withMessages(collect($unknown)
                ->mapWithKeys(fn (string $key): array => ["config.{$key}" => "Configuration field [{$key}] is not defined by [{$nodeKey}]."])
                ->all());
        }

        foreach ($fields as $key => $field) {
            if (! array_key_exists($key, $config) && array_key_exists('default', $field)) {
                $config[$key] = $field['default'];
            }
        }

        $rules = [];

        foreach ($fields as $key => $field) {
            if (! $this->isVisible($field, $config)) {
                continue;
            }

            $value = $config[$key] ?? null;
            $rules["config.{$key}"] = $this->rulesFor($field, $value);
        }

        $validated = $this->validator->make(['config' => $config], $rules)->validate();

        foreach ($fields as $key => $field) {
            $value = $validated['config'][$key] ?? null;

            if (($field['type'] ?? null) === 'cron'
                && is_string($value)
                && ! CronExpression::isValidExpression($value)) {
                throw ValidationException::withMessages([
                    "config.{$key}" => "Cron expression [{$value}] is invalid.",
                ]);
            }

            if (($field['type'] ?? null) === 'timezone'
                && is_string($value)
                && ! in_array($value, timezone_identifiers_list(), true)) {
                throw ValidationException::withMessages([
                    "config.{$key}" => "Timezone [{$value}] is invalid.",
                ]);
            }
        }

        foreach ($fields as $key => $field) {
            if (! $this->isVisible($field, $config)
                || ($field['type'] ?? null) !== 'reference'
                || ! isset($validated['config'][$key])) {
                continue;
            }

            $source = $field['source'] ?? null;

            if (! is_string($source) || ! $this->referenceProviders->has($source)) {
                throw ValidationException::withMessages([
                    "config.{$key}" => "Reference source [{$source}] is not registered.",
                ]);
            }

            $reference = $validated['config'][$key];

            if (! is_string($reference)
                || ! $this->referenceProviders->allows($source, $reference, $scope ?? new ExecutionScope)) {
                throw ValidationException::withMessages([
                    "config.{$key}" => "Reference [{$reference}] is not allowed for [{$source}].",
                ]);
            }
        }

        return $validated['config'] ?? [];
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  array<string, mixed>  $config
     */
    private function isVisible(array $field, array $config): bool
    {
        $condition = $field['show_when'] ?? null;

        if (! is_array($condition) || ! isset($condition['key'])) {
            return true;
        }

        $actual = $config[$condition['key']] ?? null;
        $expected = $condition['value'] ?? null;

        return is_array($expected)
            ? in_array($actual, $expected, true)
            : $actual === $expected;
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array<int, mixed>
     */
    private function rulesFor(array $field, mixed $value): array
    {
        $rules = [! empty($field['required']) ? 'required' : 'nullable'];

        if (! empty($field['supports_expression']) && is_string($value) && str_contains($value, '{{')) {
            return [...$rules, 'string'];
        }

        $typeRule = match ($field['type'] ?? 'string') {
            'boolean' => 'boolean',
            'integer', 'workflow_select' => 'integer',
            'number', 'slider' => 'numeric',
            'array_of_objects', 'json', 'keyvalue', 'multiselect' => 'array',
            'mixed' => null,
            default => 'string',
        };

        if ($typeRule !== null) {
            $rules[] = $typeRule;
        }

        $options = array_values(array_filter(
            $field['options'] ?? [],
            fn (mixed $option): bool => is_scalar($option) || $option === null,
        ));

        if ($options !== [] && ($field['type'] ?? null) !== 'multiselect') {
            $rules[] = Rule::in($options);
        }

        return $rules;
    }
}
