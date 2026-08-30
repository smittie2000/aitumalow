<?php

namespace Aitumalow\Console\Commands;

use Aitumalow\Models\Workflow;
use Aitumalow\Services\WorkflowService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('Validate a workflow graph and print any errors.')]
#[Signature('workflow:validate {workflow : The workflow ID to validate}')]
class ValidateWorkflowCommand extends Command
{
    public function handle(WorkflowService $service): int
    {
        $argument = $this->argument('workflow');

        if (! is_string($argument) || ! ctype_digit($argument)) {
            $this->components->error('Workflow ID must be a positive integer.');

            return self::FAILURE;
        }

        $workflow = Workflow::find((int) $argument);

        if (! $workflow) {
            $this->components->error('Workflow not found.');

            return self::FAILURE;
        }

        $errors = $service->validate($workflow);

        if (empty($errors)) {
            $this->components->info("Workflow #{$workflow->id} \"{$workflow->name}\" is valid.");

            return self::SUCCESS;
        }

        $this->components->error("Workflow #{$workflow->id} \"{$workflow->name}\" has ".count($errors).' error(s):');

        foreach ($errors as $error) {
            $this->components->bulletList([$error]);
        }

        return self::FAILURE;
    }
}
