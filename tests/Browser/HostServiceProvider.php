<?php

declare(strict_types=1);

namespace Aitumalow\Tests\Browser;

use Aitumalow\DTOs\WorkflowStart;
use Aitumalow\Facades\Workflow as WorkflowFacade;
use Aitumalow\Facades\WorkflowAutomation;
use Aitumalow\Http\EditorApiRoutes;
use Aitumalow\Mcp\WorkflowMcpServer;
use Aitumalow\Models\Workflow;
use Carbon\CarbonImmutable;
use Cron\CronExpression;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Facades\Mcp;
use RuntimeException;
use Workflow\V2\Models\WorkflowSchedule;
use Workflow\V2\Models\WorkflowScheduleHistoryEvent;
use Workflow\V2\Support\ScheduleManager;

/** Only loaded by the isolated browser host, never by the installed package. */
final class HostServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        WorkflowAutomation::register(LeadStatusChanged::class);
        WorkflowAutomation::register(EmailLeadOwner::class);
        WorkflowAutomation::register(FindOpenTickets::class);
        WorkflowAutomation::register(RemindTicketOwner::class);
        WorkflowAutomation::register(FindUnconfirmedBookings::class);
        WorkflowAutomation::register(CallBookingClient::class);

        Route::prefix('workflow-engine')->middleware('api')->name('aitumalow.')
            ->group(fn () => EditorApiRoutes::register());

        Mcp::web('/testing/mcp', WorkflowMcpServer::class)->middleware('api');

        Route::get('/testing/health', fn () => ['ready' => Lead::query()->count() === 2]);
        Route::get('/testing/leads', fn () => $this->page('leads', ['leads' => Lead::all()]));
        Route::get('/testing/scenarios', fn () => $this->page('scenarios', [
            'records' => Record::all(), 'schedules' => WorkflowSchedule::all(),
            'calls' => array_map(fn ($file) => json_decode(file_get_contents($file) ?: '{}', true, flags: JSON_THROW_ON_ERROR), glob(storage_path('calls/*.json')) ?: []),
        ]));
        Route::post('/testing/records/{record}', function (Request $request, Record $record) {
            $record->update($request->validate(['status' => $record->kind === 'ticket'
                ? 'required|in:open,closed' : 'required|in:pending,confirmed,cancelled']));

            return redirect('/testing/scenarios');
        })->middleware('api');
        Route::post('/testing/schedules/{schedule}/replay', function (WorkflowSchedule $schedule) {
            // Native cron enumeration and real Durable queue; no draft execution bypass.
            $at = $schedule->next_fire_at ?? throw new RuntimeException('Schedule has no next occurrence.');
            $last = WorkflowScheduleHistoryEvent::query()->where('workflow_schedule_id', $schedule->id)
                ->where('event_type', 'ScheduleTriggered')->whereNotNull('occurrence_at_utc')
                ->orderByDesc('occurrence_at_utc')->first();
            if ($last !== null) {
                $previous = CarbonImmutable::parse($last->getAttribute('occurrence_at_utc'), 'UTC');
                if ($previous->greaterThanOrEqualTo($at)) {
                    $at = CarbonImmutable::instance(new CronExpression($schedule->cron_expression)
                        ->getNextRunDate($previous, timeZone: $schedule->timezone));
                }
            }
            ScheduleManager::backfill($schedule, $at->copy()->subSecond(), $at->copy()->addSecond());

            return redirect('/testing/scenarios');
        })->middleware('api');
        Route::post('/testing/leads/{lead}', function (Request $request, Lead $lead) {
            $data = $request->validate(['status' => 'required|in:new,qualified,won', 'name' => 'required|string|max:100']);
            $old = $lead->status;
            $lead->update($data);
            if ($old !== $lead->status) {
                foreach (Workflow::query()->where('is_active', true)->with('activeRevision')->get() as $workflow) {
                    $definition = $workflow->activeRevision?->definition;
                    $trigger = null;
                    $nodes = $definition['nodes'] ?? [];
                    if (! is_array($nodes)) {
                        continue;
                    }
                    foreach ($nodes as $node) {
                        if (is_array($node) && ($node['id'] ?? null) === ($definition['trigger_node_id'] ?? null)) {
                            $trigger = $node;
                            break;
                        }
                    }
                    if (($trigger['key'] ?? null) !== 'test.lead.status_changed') {
                        continue;
                    }
                    WorkflowFacade::start($workflow, new WorkflowStart(payload: [[
                        'lead_id' => $lead->id, 'old_status' => $old, 'new_status' => $lead->status,
                    ]]));
                }
            }

            return redirect('/testing/leads');
        })->middleware('api');
        Route::get('/testing/inbox', function () {
            $messages = array_map(function ($file) {
                $contents = file_get_contents($file);
                if ($contents === false) {
                    throw new RuntimeException('Could not read captured email.');
                }
                $message = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
                [$headers, $body] = array_pad(explode("\n\n", $message['data'], 2), 2, '');
                preg_match('/^Subject: (.*)$/m', $headers, $subject);
                $message['subject'] = iconv_mime_decode($subject[1] ?? '') ?: 'No subject';
                $message['body'] = quoted_printable_decode($body);

                return $message;
            }, glob(storage_path('inbox/*.json')) ?: []);

            return $this->page('inbox', compact('messages'));
        });
    }

    /** @param array<string, mixed> $data */
    private function page(string $page, array $data): Response
    {
        return response()->view('browser-host::'.$page, $data);
    }

    public function register(): void
    {
        $this->loadViewsFrom(__DIR__.'/views', 'browser-host');
    }
}
