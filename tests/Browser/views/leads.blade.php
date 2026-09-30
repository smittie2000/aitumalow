<!doctype html>
<html lang="en"><meta charset="utf-8"><title>Test leads</title>
<style>body{font:16px system-ui;max-width:900px;margin:40px auto;padding:20px}nav a{margin-right:24px}article{border:1px solid #ccc;border-radius:12px;padding:24px;margin:24px 0}label{display:block;margin:12px 0}input,select,button{font:inherit;padding:8px}button{cursor:pointer}</style>
<nav><a href="/">Workflow editor</a><a href="/testing/leads">Test leads</a><a href="/testing/inbox">Captured email</a></nav>
<h1>Test leads</h1><p>Dedicated local test area. Emails are captured locally.</p>
@foreach($leads as $lead)
<article aria-label="{{ $lead->owner_name }}'s lead"><h2>{{ $lead->name }}</h2><p>Owner: {{ $lead->owner_name }} ({{ $lead->owner_email }})</p>
<form action="/testing/leads/{{ $lead->id }}" method="post">
<label>Lead name <input name="name" value="{{ $lead->name }}" required></label>
<label for="status-{{ $lead->id }}">Status</label><select id="status-{{ $lead->id }}" name="status">@foreach(['new', 'qualified', 'won'] as $status)<option value="{{ $status }}" @selected($lead->status === $status)>{{ ucfirst($status) }}</option>@endforeach</select>
<button>Save lead</button></form></article>
@endforeach
</html>
