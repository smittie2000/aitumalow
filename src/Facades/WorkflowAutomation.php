<?php

namespace Aitumalow\Facades;

use Aitumalow\Plugin\PluginManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static void plugin(\Aitumalow\Contracts\PluginInterface $plugin)
 * @method static void register(class-string $class)
 * @method static void reference(string $source, class-string<\Aitumalow\Contracts\WorkflowReferenceProvider> $provider)
 * @method static void subject(class-string<\Aitumalow\Contracts\WorkflowSubject>|\Aitumalow\Contracts\WorkflowSubject $subject)
 * @method static void scheduledSource(class-string<\Aitumalow\Contracts\ScheduledSubjectSource>|\Aitumalow\Contracts\ScheduledSubjectSource $source)
 * @method static \Aitumalow\Plugin\PluginRegistry plugins()
 * @method static \Aitumalow\Plugin\PluginContext context()
 *
 * @see PluginManager
 */
class WorkflowAutomation extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PluginManager::class;
    }
}
