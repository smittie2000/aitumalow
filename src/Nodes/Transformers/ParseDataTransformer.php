<?php

namespace Aitumalow\Nodes\Transformers;

use Aitumalow\Attributes\WorkflowNode;
use Aitumalow\DTOs\NodeInput;
use Aitumalow\DTOs\NodeOutput;
use Aitumalow\Enums\NodeType;
use Aitumalow\Nodes\BaseNode;

#[WorkflowNode(key: 'core.parse_data', name: 'Parse Data', category: 'Transformers', type: NodeType::Transformer)]
class ParseDataTransformer extends BaseNode
{
    public static function configSchema(): array
    {
        return [
            ['key' => 'source_field', 'type' => 'string', 'label' => 'Source field', 'required' => true, 'supports_expression' => true],
            ['key' => 'format', 'type' => 'select', 'label' => 'Format', 'required' => true, 'options' => ['json', 'csv', 'key_value']],
            ['key' => 'target_field', 'type' => 'string', 'label' => 'Target field', 'required' => true],
        ];
    }

    /** @param array<string, mixed> $config */
    public function execute(NodeInput $input, array $config): NodeOutput
    {
        $results = [];

        foreach ($input->items as $item) {
            try {
                $raw = data_get($item, $config['source_field'], '');
                $parsed = $this->parse((string) $raw, $config['format']);

                $results[] = array_merge($item, [$config['target_field'] => $parsed]);
            } catch (\Throwable $e) {
                return NodeOutput::ports([
                    'main' => $results,
                    'error' => [array_merge($item, ['error' => $e->getMessage()])],
                ]);
            }
        }

        return NodeOutput::main($results);
    }

    private function parse(string $raw, string $format): mixed
    {
        return match ($format) {
            'json' => json_decode($raw, true, 512, JSON_THROW_ON_ERROR),
            'csv' => $this->parseCsv($raw),
            'key_value' => $this->parseKeyValue($raw),
            default => $raw,
        };
    }

    /** @return array<int, array<array-key, string|null>> */
    private function parseCsv(string $raw): array
    {
        if (trim($raw) === '') {
            return [];
        }

        $lines = str_getcsv($raw, "\n", escape: '\\');
        $headers = str_getcsv(array_shift($lines), escape: '\\');
        $result = [];

        foreach ($lines as $line) {
            $values = str_getcsv($line, escape: '\\');
            if (count($values) === count($headers)) {
                $result[] = array_combine($headers, $values);
            }
        }

        return $result;
    }

    /** @return array<array-key, mixed> */
    private function parseKeyValue(string $raw): array
    {
        parse_str($raw, $result);

        return $result;
    }
}
