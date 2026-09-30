<!doctype html>
<html lang="en"><meta charset="utf-8"><title>Scheduled scenarios</title>
<style>body{font:16px system-ui;max-width:1000px;margin:40px auto;padding:20px}nav a{margin-right:24px}article{border:1px solid #ccc;border-radius:12px;padding:20px;margin:16px 0}input,select,button{font:inherit;padding:8px}button{cursor:pointer}pre{white-space:pre-wrap}</style>
<nav><a href="/">Workflow editor</a><a href="/testing/leads">Test leads</a><a href="/testing/inbox">Captured email</a><a href="/testing/scenarios">Scheduled scenarios</a></nav>
<h1>Scheduled scenarios</h1><p>Test records and captured call requests. Replay uses native schedule backfill and the real worker; it does not wait for the wall clock. No phone calls leave this area.</p>
<h2>Published schedules</h2>
@foreach($schedules as $schedule)
<article aria-label="Schedule {{ $schedule->schedule_id }}">
<h3>{{ $schedule->notes }}</h3><p>Repeat: {{ $schedule->cron_expression }} · Timezone: {{ $schedule->timezone }} · Status: {{ $schedule->status->value }}</p>
<p>Next occurrence: {{ $schedule->next_fire_at?->setTimezone($schedule->timezone)->format('Y-m-d H:i') }}</p>
<form action="/testing/schedules/{{ $schedule->id }}/replay" method="post"><button>Replay next unplayed occurrence</button></form></article>
@endforeach
<h2>Host records</h2>
@foreach($records as $record)
<article aria-label="{{ $record->name }}"><h3>{{ $record->name }}</h3><p>{{ $record->kind }} · {{ json_encode($record->data) }}</p>
<form action="/testing/records/{{ $record->id }}" method="post"><label for="record-{{ $record->id }}">Status</label>
<select id="record-{{ $record->id }}" name="status">@foreach($record->kind === 'ticket' ? ['open', 'closed'] : ['pending', 'confirmed', 'cancelled'] as $status)<option @selected($record->status === $status)>{{ $status }}</option>@endforeach</select><button>Save record</button></form></article>
@endforeach
<h2>Captured calls</h2>
@foreach($calls as $call)
<article aria-label="Captured call"><h3>{{ $call['client'] }}</h3><p>{{ $call['phone'] }}</p><p>{{ $call['instructions'] }}</p><pre>{{ json_encode($call['booking'], JSON_PRETTY_PRINT) }}</pre></article>
@endforeach
</html>
