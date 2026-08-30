<?php

namespace Aitumalow\Exceptions;

class PluginException extends WorkflowException
{
    public static function alreadyRegistered(string $id): self
    {
        return new self("Plugin '{$id}' is already registered.");
    }
}
