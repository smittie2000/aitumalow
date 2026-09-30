<!doctype html>
<html lang="en"><meta charset="utf-8"><title>Captured email</title>
<style>body{font:16px system-ui;max-width:900px;margin:40px auto;padding:20px}nav a{margin-right:24px}article{border:1px solid #ccc;border-radius:12px;padding:24px;margin:24px 0}pre{white-space:pre-wrap}</style>
<nav><a href="/">Workflow editor</a><a href="/testing/leads">Test leads</a><a href="/testing/inbox">Captured email</a></nav>
<h1>Captured email</h1><p>{{ count($messages) }} {{ count($messages) === 1 ? 'message' : 'messages' }}</p><a href="/testing/inbox">Refresh inbox</a>
@foreach($messages as $message)
<article aria-label="Captured email"><h2>{{ $message['subject'] }}</h2><p>To: {{ implode(', ', $message['recipients']) }}</p><pre>{{ $message['body'] }}</pre><details><summary>Email source</summary><pre>{{ $message['data'] }}</pre></details></article>
@endforeach
</html>
