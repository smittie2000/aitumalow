<?php

use Aitumalow\Engine\ExpressionEvaluator;
use Aitumalow\Exceptions\ExpressionException;

beforeEach(function () {
    $this->evaluator = new ExpressionEvaluator;
});

// ── Dot-notation variable access ────────────────────────────────

it('resolves a simple variable', function () {
    $result = $this->evaluator->resolve('{{ item.name }}', ['item' => ['name' => 'John']]);

    expect($result)->toBe('John');
});

it('resolves nested dot-notation', function () {
    $result = $this->evaluator->resolve('{{ item.address.city }}', [
        'item' => ['address' => ['city' => 'Berlin']],
    ]);

    expect($result)->toBe('Berlin');
});

it('resolves numeric keys in dot-notation', function () {
    $result = $this->evaluator->resolve('{{ trigger.0.email }}', [
        'trigger' => [['email' => 'test@example.com']],
    ]);

    expect($result)->toBe('test@example.com');
});

it('returns null for missing paths', function () {
    $result = $this->evaluator->resolve('{{ item.nonexistent }}', ['item' => []]);

    expect($result)->toBeNull();
});

// ── Literals ────────────────────────────────────────────────────

it('parses integer literals', function () {
    expect($this->evaluator->evaluate('42', []))->toBe(42);
});

it('parses float literals', function () {
    expect($this->evaluator->evaluate('3.14', []))->toBe(3.14);
});

it('parses string literals', function () {
    expect($this->evaluator->evaluate("'hello'", []))->toBe('hello')
        ->and($this->evaluator->evaluate('"world"', []))->toBe('world');
});

it('parses boolean literals', function () {
    expect($this->evaluator->evaluate('true', []))->toBeTrue()
        ->and($this->evaluator->evaluate('false', []))->toBeFalse();
});

it('parses null literal', function () {
    expect($this->evaluator->evaluate('null', []))->toBeNull();
});

// ── Arithmetic ──────────────────────────────────────────────────

it('performs addition', function () {
    expect($this->evaluator->evaluate('2 + 3', []))->toBe(5);
});

it('performs subtraction', function () {
    expect($this->evaluator->evaluate('10 - 4', []))->toBe(6);
});

it('performs multiplication', function () {
    expect($this->evaluator->evaluate('3 * 7', []))->toBe(21);
});

it('performs division', function () {
    expect($this->evaluator->evaluate('20 / 4', []))->toBe(5);
});

it('performs modulo', function () {
    expect($this->evaluator->evaluate('10 % 3', []))->toBe(1);
});

it('respects operator precedence', function () {
    expect($this->evaluator->evaluate('2 + 3 * 4', []))->toBe(14);
});

it('throws on division by zero', function () {
    $this->evaluator->evaluate('10 / 0', []);
})->throws(ExpressionException::class, 'Division by zero');

// ── String concatenation ────────────────────────────────────────

it('concatenates strings with +', function () {
    $result = $this->evaluator->evaluate("'hello' + ' ' + 'world'", []);

    expect($result)->toBe('hello world');
});

// ── Comparison ──────────────────────────────────────────────────

it('evaluates equality', function () {
    expect($this->evaluator->evaluate('1 == 1', []))->toBeTrue()
        ->and($this->evaluator->evaluate('1 == 2', []))->toBeFalse();
});

it('evaluates inequality', function () {
    expect($this->evaluator->evaluate('1 != 2', []))->toBeTrue();
});

it('evaluates greater than', function () {
    expect($this->evaluator->evaluate('5 > 3', []))->toBeTrue()
        ->and($this->evaluator->evaluate('3 > 5', []))->toBeFalse();
});

it('evaluates less than', function () {
    expect($this->evaluator->evaluate('3 < 5', []))->toBeTrue();
});

it('evaluates >= and <=', function () {
    expect($this->evaluator->evaluate('5 >= 5', []))->toBeTrue()
        ->and($this->evaluator->evaluate('5 <= 5', []))->toBeTrue();
});

// ── Logical ─────────────────────────────────────────────────────

it('evaluates logical AND', function () {
    expect($this->evaluator->evaluate('true && true', []))->toBeTrue()
        ->and($this->evaluator->evaluate('true && false', []))->toBeFalse();
});

it('evaluates logical OR', function () {
    expect($this->evaluator->evaluate('false || true', []))->toBeTrue()
        ->and($this->evaluator->evaluate('false || false', []))->toBeFalse();
});

it('evaluates logical NOT', function () {
    expect($this->evaluator->evaluate('!false', []))->toBeTrue()
        ->and($this->evaluator->evaluate('!true', []))->toBeFalse();
});

// ── Ternary ─────────────────────────────────────────────────────

it('evaluates ternary', function () {
    expect($this->evaluator->evaluate("true ? 'yes' : 'no'", []))->toBe('yes')
        ->and($this->evaluator->evaluate("false ? 'yes' : 'no'", []))->toBe('no');
});

// ── Array literal ───────────────────────────────────────────────

it('parses array literals', function () {
    $result = $this->evaluator->evaluate('[1, 2, 3]', []);

    expect($result)->toBe([1, 2, 3]);
});

// ── Whitelisted functions ───────────────────────────────────────

it('calls upper()', function () {
    expect($this->evaluator->evaluate("upper('hello')", []))->toBe('HELLO');
});

it('calls lower()', function () {
    expect($this->evaluator->evaluate("lower('WORLD')", []))->toBe('world');
});

it('calls trim()', function () {
    expect($this->evaluator->evaluate("trim('  hi  ')", []))->toBe('hi');
});

it('calls length() on string', function () {
    expect($this->evaluator->evaluate("length('abcde')", []))->toBe(5);
});

it('calls length() on array', function () {
    expect($this->evaluator->evaluate('length([1, 2, 3])', []))->toBe(3);
});

it('calls contains()', function () {
    expect($this->evaluator->evaluate("contains('hello world', 'world')", []))->toBeTrue()
        ->and($this->evaluator->evaluate("contains('hello world', 'xyz')", []))->toBeFalse();
});

it('calls round()', function () {
    expect($this->evaluator->evaluate('round(3.7)', []))->toBe(4.0)
        ->and($this->evaluator->evaluate('round(3.456, 2)', []))->toBe(3.46);
});

it('calls sum()', function () {
    expect($this->evaluator->evaluate('sum([1, 2, 3, 4])', []))->toBe(10);
});

it('calls count()', function () {
    expect($this->evaluator->evaluate('count([10, 20, 30])', []))->toBe(3);
});

it('calls first() and last()', function () {
    expect($this->evaluator->evaluate('first([10, 20, 30])', []))->toBe(10)
        ->and($this->evaluator->evaluate('last([10, 20, 30])', []))->toBe(30);
});

it('calls join()', function () {
    expect($this->evaluator->evaluate("join(', ', ['a', 'b', 'c'])", []))->toBe('a, b, c');
});

it('calls split()', function () {
    expect($this->evaluator->evaluate("split(',', 'a,b,c')", []))->toBe(['a', 'b', 'c']);
});

// ── Forbidden functions ────────────────────────────────────────

it('rejects unknown functions', function () {
    $this->evaluator->evaluate("eval('code')", []);
})->throws(ExpressionException::class, 'Unknown function');

it('rejects system() calls', function () {
    $this->evaluator->evaluate("system('ls')", []);
})->throws(ExpressionException::class, 'Unknown function');

// ── Template interpolation ──────────────────────────────────────

it('interpolates mixed template', function () {
    $result = $this->evaluator->resolve(
        'Hello {{ item.name }}, you are {{ item.age }} years old.',
        ['item' => ['name' => 'Jane', 'age' => 30]],
    );

    expect($result)->toBe('Hello Jane, you are 30 years old.');
});

it('returns raw type for single expression template', function () {
    $result = $this->evaluator->resolve('{{ 42 }}', []);

    expect($result)->toBe(42);
});

// ── resolveConfig ───────────────────────────────────────────────

it('resolves expressions in config arrays', function () {
    $config = [
        'to' => '{{ item.email }}',
        'subject' => 'Hello {{ item.name }}',
        'static' => 'no expressions here',
    ];

    $result = $this->evaluator->resolveConfig($config, [
        'item' => ['email' => 'a@b.com', 'name' => 'Bob'],
    ]);
    expect($result)->toMatchArray(['to' => 'a@b.com', 'subject' => 'Hello Bob', 'static' => 'no expressions here']);
});

// ── Custom function registration ────────────────────────────────

it('allows registering custom functions', function () {
    $this->evaluator->registerFunction('double', fn (int $v) => $v * 2);

    expect($this->evaluator->evaluate('double(5)', []))->toBe(10);
});

// ── Complex expression with variables ───────────────────────────

it('evaluates expression with variable arithmetic', function () {
    $result = $this->evaluator->evaluate('item.price * item.qty', [
        'item' => ['price' => 10, 'qty' => 3],
    ]);

    expect($result)->toBe(30);
});

it('evaluates combined variable comparison', function () {
    $result = $this->evaluator->evaluate("item.status == 'active' && item.age > 18", [
        'item' => ['status' => 'active', 'age' => 25],
    ]);

    expect($result)->toBeTrue();
});
