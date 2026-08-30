<?php

namespace Aitumalow\Engine;

use Aitumalow\Contracts\ExpressionEvaluatorInterface;
use Aitumalow\Exceptions\ExpressionException;
use Aitumalow\Registry\ExpressionFunctionRegistry;

class ExpressionEvaluator implements ExpressionEvaluatorInterface
{
    /** @var array<string, callable> Whitelisted functions available in expressions. */
    private array $functions;

    /** @var array<string, callable> Functions registered directly on this scoped evaluator. */
    private array $localFunctions = [];

    private string $expression;

    private int $pos;

    private int $length;

    public function __construct(
        private readonly ?ExpressionFunctionRegistry $registeredFunctions = null,
    ) {
        $this->functions = $this->defaultFunctions();
    }

    /**
     * Register a custom function for use in expressions.
     */
    public function registerFunction(string $name, callable $fn): void
    {
        $this->localFunctions[$name] = $fn;
    }

    /**
     * Resolve a template string containing {{ expression }} blocks.
     *
     * If the entire string is a single {{ expr }}, returns the raw value (preserving type).
     * If the string contains mixed text + {{ expr }}, all results are cast to string.
     *
     * @param  array<string, mixed>  $variables
     */
    public function resolve(string $template, array $variables): mixed
    {
        // Single expression — return raw value
        if (preg_match('/^\{\{\s*(.+?)\s*\}\}$/s', trim($template), $m)) {
            return $this->evaluate($m[1], $variables);
        }

        // Mixed template — interpolate all {{ }} blocks into the string
        return preg_replace_callback('/\{\{\s*(.+?)\s*\}\}/s', function (array $m) use ($variables) {
            $result = $this->evaluate($m[1], $variables);

            return is_scalar($result) ? (string) $result : json_encode($result, JSON_THROW_ON_ERROR);
        }, $template);
    }

    /**
     * Recursively resolve all expression strings within a config array.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     */
    public function resolveConfig(array $config, array $variables): array
    {
        array_walk_recursive($config, function (mixed &$value) use ($variables) {
            if (is_string($value) && str_contains($value, '{{')) {
                $value = $this->resolve($value, $variables);
            }
        });

        return $config;
    }

    // ──────────────────────────────────────────────────────────────────
    //  Recursive Descent Parser
    // ──────────────────────────────────────────────────────────────────

    /**
     * Evaluate a single expression string.
     *
     * @param  array<string, mixed>  $variables
     */
    public function evaluate(string $expression, array $variables): mixed
    {
        $this->expression = trim($expression);
        $this->pos = 0;
        $this->length = strlen($this->expression);

        $result = $this->parseTernary($variables);

        $this->skipWhitespace();

        if ($this->pos < $this->length) {
            throw new ExpressionException(
                "Unexpected character at position {$this->pos}: '{$this->expression[$this->pos]}'"
            );
        }

        return $result;
    }

    // ── Ternary: condition ? value_a : value_b ──

    /** @param array<string, mixed> $vars */
    private function parseTernary(array $vars): mixed
    {
        $condition = $this->parseOr($vars);

        $this->skipWhitespace();

        if ($this->peek() === '?') {
            $this->advance();
            $trueVal = $this->parseTernary($vars);
            $this->expect(':');
            $falseVal = $this->parseTernary($vars);

            return $condition ? $trueVal : $falseVal;
        }

        return $condition;
    }

    // ── Logical OR: || ──

    /** @param array<string, mixed> $vars */
    private function parseOr(array $vars): mixed
    {
        $left = $this->parseAnd($vars);

        while ($this->matchSequence('||')) {
            $right = $this->parseAnd($vars);
            $left = $left || $right;
        }

        return $left;
    }

    // ── Logical AND: && ──

    /** @param array<string, mixed> $vars */
    private function parseAnd(array $vars): mixed
    {
        $left = $this->parseEquality($vars);

        while ($this->matchSequence('&&')) {
            $right = $this->parseEquality($vars);
            $left = $left && $right;
        }

        return $left;
    }

    // ── Equality: ==, != ──

    /** @param array<string, mixed> $vars */
    private function parseEquality(array $vars): mixed
    {
        $left = $this->parseComparison($vars);

        while (true) {
            $this->skipWhitespace();

            if ($this->matchSequence('==')) {
                $left = $left == $this->parseComparison($vars);
            } elseif ($this->matchSequence('!=')) {
                $left = $left != $this->parseComparison($vars);
            } else {
                break;
            }
        }

        return $left;
    }

    // ── Comparison: >, <, >=, <= ──

    /** @param array<string, mixed> $vars */
    private function parseComparison(array $vars): mixed
    {
        $left = $this->parseAddition($vars);

        while (true) {
            $this->skipWhitespace();

            if ($this->matchSequence('>=')) {
                $left = $left >= $this->parseAddition($vars);
            } elseif ($this->matchSequence('<=')) {
                $left = $left <= $this->parseAddition($vars);
            } elseif ($this->peek() === '>' && ! $this->peekAt(1, '=')) {
                $this->advance();
                $left = $left > $this->parseAddition($vars);
            } elseif ($this->peek() === '<' && ! $this->peekAt(1, '=')) {
                $this->advance();
                $left = $left < $this->parseAddition($vars);
            } else {
                break;
            }
        }

        return $left;
    }

    // ── Addition / Subtraction: +, - ──

    /** @param array<string, mixed> $vars */
    private function parseAddition(array $vars): mixed
    {
        $left = $this->parseMultiplication($vars);

        while (true) {
            $this->skipWhitespace();
            $ch = $this->peek();

            if ($ch === '+') {
                $this->advance();
                $right = $this->parseMultiplication($vars);

                if (is_string($left) || is_string($right)) {
                    $left = $left.$right; // string concatenation
                } else {
                    $left = $left + $right;
                }
            } elseif ($ch === '-') {
                $this->advance();
                $left -= $this->parseMultiplication($vars);
            } else {
                break;
            }
        }

        return $left;
    }

    // ── Multiplication / Division / Modulo: *, /, % ──

    /** @param array<string, mixed> $vars */
    private function parseMultiplication(array $vars): mixed
    {
        $left = $this->parseUnary($vars);

        while (true) {
            $this->skipWhitespace();
            $ch = $this->peek();

            if ($ch === '*') {
                $this->advance();
                $left *= $this->parseUnary($vars);
            } elseif ($ch === '/') {
                $this->advance();
                $divisor = $this->parseUnary($vars);

                if ($divisor == 0) {
                    throw new ExpressionException('Division by zero.');
                }

                $left /= $divisor;
            } elseif ($ch === '%') {
                $this->advance();
                $left %= $this->parseUnary($vars);
            } else {
                break;
            }
        }

        return $left;
    }

    // ── Unary: !, - (negation) ──

    /** @param array<string, mixed> $vars */
    private function parseUnary(array $vars): mixed
    {
        $this->skipWhitespace();
        $ch = $this->peek();

        if ($ch === '!') {
            $this->advance();

            return ! $this->parseUnary($vars);
        }

        if ($ch === '-' && ! is_numeric($this->peekChar(1))) {
            $this->advance();

            return -$this->parseUnary($vars);
        }

        return $this->parsePrimary($vars);
    }

    // ── Primary: literals, variables, function calls, parentheses, arrays ──

    /** @param array<string, mixed> $vars */
    private function parsePrimary(array $vars): mixed
    {
        $this->skipWhitespace();
        $ch = $this->peek();

        // Parenthesized expression
        if ($ch === '(') {
            $this->advance();
            $val = $this->parseTernary($vars);
            $this->expect(')');

            return $val;
        }

        // Array literal [...]
        if ($ch === '[') {
            return $this->parseArrayLiteral($vars);
        }

        // String literal (single or double quoted)
        if ($ch === "'" || $ch === '"') {
            return $this->parseString();
        }

        // Number
        if (is_numeric($ch) || ($ch === '-' && is_numeric($this->peekChar(1)))) {
            return $this->parseNumber();
        }

        // Boolean / null keywords
        $keyword = $this->peekWord();
        if (in_array($keyword, ['true', 'false', 'null'], true)) {
            $this->advanceBy(strlen($keyword));

            return match ($keyword) {
                'true' => true,
                'false' => false,
                'null' => null,
            };
        }

        // Identifier: variable path or function call
        return $this->parseIdentifier($vars);
    }

    /**
     * @param  array<string, mixed>  $vars
     * @return list<mixed>
     */
    private function parseArrayLiteral(array $vars): array
    {
        $this->expect('[');
        $items = [];

        $this->skipWhitespace();
        if ($this->peek() !== ']') {
            $items[] = $this->parseTernary($vars);

            while ($this->peek() === ',') {
                $this->advance();
                $this->skipWhitespace();
                if ($this->peek() === ']') {
                    break; // trailing comma
                }
                $items[] = $this->parseTernary($vars);
            }
        }

        $this->expect(']');

        return $items;
    }

    private function parseString(): string
    {
        $quote = $this->expression[$this->pos];
        $this->advance();
        $result = '';

        while ($this->pos < $this->length) {
            $ch = $this->expression[$this->pos];

            if ($ch === '\\' && $this->pos + 1 < $this->length) {
                $this->advance();
                $result .= $this->expression[$this->pos];
                $this->advance();

                continue;
            }

            if ($ch === $quote) {
                $this->advance();

                return $result;
            }

            $result .= $ch;
            $this->advance();
        }

        throw new ExpressionException('Unterminated string literal.');
    }

    private function parseNumber(): int|float
    {
        $start = $this->pos;

        if ($this->peek() === '-') {
            $this->advance();
        }

        while ($this->pos < $this->length && (is_numeric($this->expression[$this->pos]) || $this->expression[$this->pos] === '.')) {
            $this->advance();
        }

        $numStr = substr($this->expression, $start, $this->pos - $start);

        return str_contains($numStr, '.') ? (float) $numStr : (int) $numStr;
    }

    /**
     * Parse an identifier which may be:
     *  - A function call: upper(item.name)
     *  - A dot-notation variable path: node.5.main.0.email
     */
    /** @param array<string, mixed> $vars */
    private function parseIdentifier(array $vars): mixed
    {
        $name = $this->readIdentifierName();

        if ($name === '') {
            throw new ExpressionException(
                "Unexpected character at position {$this->pos}: '"
                .($this->peek() ?? 'EOF')."'"
            );
        }

        $this->skipWhitespace();

        // Function call
        if ($this->peek() === '(') {
            return $this->parseFunctionCall($name, $vars);
        }

        // Dot-notation variable access
        $path = $name;
        while ($this->peek() === '.') {
            $this->advance();
            $segment = $this->readIdentifierName();
            if ($segment === '') {
                break;
            }
            $path .= '.'.$segment;
        }

        return data_get($vars, $path);
    }

    /** @param array<string, mixed> $vars */
    private function parseFunctionCall(string $name, array $vars): mixed
    {
        $function = $this->localFunctions[$name]
            ?? $this->registeredFunctions?->get($name)
            ?? $this->functions[$name]
            ?? null;

        if ($function === null) {
            throw new ExpressionException("Unknown function: {$name}");
        }

        $this->expect('(');
        $args = [];

        $this->skipWhitespace();
        if ($this->peek() !== ')') {
            $args[] = $this->parseTernary($vars);

            while ($this->peek() === ',') {
                $this->advance();
                $args[] = $this->parseTernary($vars);
            }
        }

        $this->expect(')');

        return $function(...$args);
    }

    // ──────────────────────────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────────────────────────

    private function peek(): ?string
    {
        return $this->pos < $this->length ? $this->expression[$this->pos] : null;
    }

    private function peekChar(int $offset): ?string
    {
        $idx = $this->pos + $offset;

        return $idx < $this->length ? $this->expression[$idx] : null;
    }

    private function peekAt(int $offset, string $char): bool
    {
        $idx = $this->pos + $offset;

        return $idx < $this->length && $this->expression[$idx] === $char;
    }

    private function advance(): void
    {
        $this->pos++;
    }

    private function advanceBy(int $n): void
    {
        $this->pos += $n;
    }

    private function expect(string $char): void
    {
        $this->skipWhitespace();

        if ($this->peek() !== $char) {
            throw new ExpressionException(
                "Expected '{$char}' at position {$this->pos}, got '"
                .($this->peek() ?? 'EOF')."'"
            );
        }

        $this->advance();
    }

    private function matchSequence(string $seq): bool
    {
        $this->skipWhitespace();
        $len = strlen($seq);

        if (substr($this->expression, $this->pos, $len) === $seq) {
            $this->pos += $len;

            return true;
        }

        return false;
    }

    private function skipWhitespace(): void
    {
        while ($this->pos < $this->length && ctype_space($this->expression[$this->pos])) {
            $this->pos++;
        }
    }

    private function peekWord(): string
    {
        $start = $this->pos;
        $word = '';

        while ($start < $this->length && ctype_alpha($this->expression[$start])) {
            $word .= $this->expression[$start];
            $start++;
        }

        return $word;
    }

    private function readIdentifierName(): string
    {
        $start = $this->pos;

        while ($this->pos < $this->length &&
            (ctype_alnum($this->expression[$this->pos]) || $this->expression[$this->pos] === '_')) {
            $this->pos++;
        }

        return substr($this->expression, $start, $this->pos - $start);
    }

    // ──────────────────────────────────────────────────────────────────
    //  Default Whitelisted Functions
    // ──────────────────────────────────────────────────────────────────

    /** @return array<string, callable> */
    private function defaultFunctions(): array
    {
        $strict = config('aitumalow.expression_mode', 'safe') === 'strict';

        if ($strict) {
            return [];
        }

        return [
            // String
            'upper' => fn (mixed $v): string => strtoupper((string) $v),
            'lower' => fn (mixed $v): string => strtolower((string) $v),
            'trim' => fn (mixed $v): string => trim((string) $v),
            'length' => fn (mixed $v): int => is_array($v) ? count($v) : strlen((string) $v),
            'substr' => fn (string $s, int $start, ?int $len = null): string => $len !== null ? substr($s, $start, $len) : substr($s, $start),
            'replace' => fn (string $search, string $replace, string $subject): string => str_replace($search, $replace, $subject),
            'contains' => str_contains(...),
            'starts_with' => str_starts_with(...),
            'ends_with' => str_ends_with(...),
            'split' => explode(...),
            'join' => implode(...),

            // Number
            'round' => round(...),
            'ceil' => ceil(...),
            'floor' => floor(...),
            'abs' => abs(...),
            'min' => fn (mixed ...$vals): mixed => $vals === [] ? null : min($vals),
            'max' => fn (mixed ...$vals): mixed => $vals === [] ? null : max($vals),
            'sum' => array_sum(...),
            'avg' => fn (array $arr): int|float => count($arr) ? array_sum($arr) / count($arr) : 0,

            // Array
            'count' => count(...),
            'first' => fn (array $arr): mixed => $arr[0] ?? null,
            'last' => fn (array $arr): mixed => end($arr) ?: null,
            'pluck' => array_column(...),
            'flatten' => fn (array $arr): array => array_merge(...array_map(fn ($v) => is_array($v) ? $v : [$v], $arr)),
            'unique' => fn (array $arr): array => array_values(array_unique($arr)),
            'sort' => function (array $arr): array {
                sort($arr);

                return $arr;
            },

            // Date
            'now' => fn (): string => now()->toISOString(),
            'date_format' => function (string $date, string $format): string {
                $timestamp = strtotime($date);

                if ($timestamp === false) {
                    throw new ExpressionException("Invalid date: {$date}");
                }

                return date($format, $timestamp);
            },
            'date_diff' => fn (string $d1, string $d2, string $unit = 'days'): int => match ($unit) {
                'seconds' => abs(strtotime($d1) - strtotime($d2)),
                'minutes' => (int) (abs(strtotime($d1) - strtotime($d2)) / 60),
                'hours' => (int) (abs(strtotime($d1) - strtotime($d2)) / 3600),
                'days' => (int) (abs(strtotime($d1) - strtotime($d2)) / 86400),
                default => (int) (abs(strtotime($d1) - strtotime($d2)) / 86400),
            },

            // Type casting
            'int' => fn (mixed $v): int => (int) $v,
            'float' => fn (mixed $v): float => (float) $v,
            'string' => fn (mixed $v): string => is_array($v) ? json_encode($v, JSON_THROW_ON_ERROR) : (string) $v,
            'bool' => fn (mixed $v): bool => (bool) $v,
            'json_encode' => json_encode(...),
            'json_decode' => fn (string $v): mixed => json_decode($v, true),
        ];
    }
}
