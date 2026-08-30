<?php

declare(strict_types=1);

namespace Aitumalow\Registry;

use Aitumalow\Contracts\WorkflowSubject;
use InvalidArgumentException;

final class WorkflowSubjectRegistry
{
    private const string KEY_PATTERN = '/\A[a-z][a-z0-9]*(?:\.[a-z][a-z0-9_-]*)+\z/';

    /** @var array<string, class-string<WorkflowSubject>|WorkflowSubject> */
    private array $subjects = [];

    /** @param class-string<WorkflowSubject>|WorkflowSubject $subject */
    public function register(string|WorkflowSubject $subject): void
    {
        $instance = is_string($subject) ? app($subject) : $subject;
        $key = $instance->key();

        if (strlen($key) > 100 || ! preg_match(self::KEY_PATTERN, $key)) {
            throw new InvalidArgumentException(
                "Subject key [{$key}] must be a lowercase namespaced key such as app.calendar_event.",
            );
        }

        if (isset($this->subjects[$key])) {
            throw new InvalidArgumentException("Subject key [{$key}] is already registered.");
        }

        $this->subjects[$key] = $subject;
    }

    public function get(string $key): WorkflowSubject
    {
        $subject = $this->subjects[$key]
            ?? throw new InvalidArgumentException("Workflow subject [{$key}] is not registered.");

        return is_string($subject) ? app($subject) : $subject;
    }

    public function has(string $key): bool
    {
        return isset($this->subjects[$key]);
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->subjects);
    }
}
