<?php

declare(strict_types=1);

namespace Aitumalow\Mcp;

use Aitumalow\Exceptions\WorkflowDraftConflictException;
use Aitumalow\Exceptions\WorkflowValidationException;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use LogicException;

trait HandlesWorkflowErrors
{
    /** @param Closure(): array<string, mixed> $operation */
    private function respond(Closure $operation): Response|ResponseFactory
    {
        try {
            return Response::structured($operation());
        } catch (ValidationException $exception) {
            return Response::error(implode(' ', $exception->validator->errors()->all()));
        } catch (WorkflowValidationException $exception) {
            return Response::error(implode(' ', $exception->errors));
        } catch (ModelNotFoundException) {
            return Response::error('The requested workflow item could not be found.');
        } catch (WorkflowDraftConflictException|AuthorizationException|LogicException $exception) {
            return Response::error($exception->getMessage());
        }
    }
}
