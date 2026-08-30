<?php

declare(strict_types=1);

namespace Aitumalow\Registry;

use Aitumalow\Contracts\ScheduledSubjectSource;
use InvalidArgumentException;

final class ScheduledSubjectSourceRegistry
{
    private const string KEY_PATTERN = '/\A[a-z][a-z0-9]*(?:\.[a-z][a-z0-9_-]*)+\z/';

    /** @var array<string, class-string<ScheduledSubjectSource>|ScheduledSubjectSource> */
    private array $sources = [];

    /** @param class-string<ScheduledSubjectSource>|ScheduledSubjectSource $source */
    public function register(string|ScheduledSubjectSource $source): void
    {
        $instance = is_string($source) ? app($source) : $source;
        $key = $instance->key();

        if (strlen($key) > 100 || ! preg_match(self::KEY_PATTERN, $key)) {
            throw new InvalidArgumentException(
                "Scheduled subject source key [{$key}] must be a lowercase namespaced key such as app.daily_records.",
            );
        }

        if (isset($this->sources[$key])) {
            throw new InvalidArgumentException("Scheduled subject source [{$key}] is already registered.");
        }

        $this->sources[$key] = $source;
    }

    public function has(string $key): bool
    {
        return isset($this->sources[$key]);
    }

    public function get(string $key): ScheduledSubjectSource
    {
        $source = $this->sources[$key] ?? throw new InvalidArgumentException("Scheduled subject source [{$key}] is not registered.");

        return is_string($source) ? app($source) : $source;
    }
}
