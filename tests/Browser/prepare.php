<?php

declare(strict_types=1);
use Aitumalow\Tests\Browser\Lead;
use Aitumalow\Tests\Browser\Record;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

// This path is fixed and dedicated; never accepts a host application's DB settings.
$path = dirname(__DIR__, 2).'/storage/browser-testing';
if (! is_dir($path)) {
    mkdir($path, 0775, true);
}
// Recreate all SQLite files after the launcher has refused any running host.
// A worker stopped during WAL writes can otherwise leave stale sidecar files.
foreach (['database.sqlite', 'database.sqlite-wal', 'database.sqlite-shm'] as $filename) {
    if (is_file($path.'/'.$filename)) {
        unlink($path.'/'.$filename);
    }
}
touch($path.'/database.sqlite');
$app = require __DIR__.'/bootstrap.php';
$status = Artisan::call('migrate', ['--force' => true]);
echo Artisan::output();
if ($status !== 0) {
    exit($status);
}
$schema = Schema::getFacadeRoot();
$schema->create('jobs', function ($table): void {
    $table->bigIncrements('id');
    $table->string('queue')->index();
    $table->longText('payload');
    $table->unsignedTinyInteger('attempts');
    $table->unsignedInteger('reserved_at')->nullable();
    $table->unsignedInteger('available_at');
    $table->unsignedInteger('created_at');
});
$schema->create('browser_leads', function ($table): void {
    $table->id();
    $table->string('name');
    $table->string('status');
    $table->string('owner_name');
    $table->string('owner_email');
    $table->timestamps();
});
foreach ([['Alice', 'alice@example.test'], ['Bob', 'bob@example.test']] as [$name, $email]) {
    Lead::create(['name' => $name.' test lead', 'status' => 'new', 'owner_name' => $name, 'owner_email' => $email]);
}
$schema->create('browser_records', function ($table): void {
    $table->id();
    $table->string('kind');
    $table->string('name');
    $table->string('status');
    $table->json('data');
    $table->timestamps();
});
foreach ([['Printer offline', 'open', 'support@example.test'], ['Invoice question', 'open', 'accounts@example.test'], ['Resolved ticket', 'closed', 'closed@example.test']] as [$name, $status, $email]) {
    Record::create(['kind' => 'ticket', 'name' => $name, 'status' => $status, 'data' => ['owner_email' => $email]]);
}
foreach ([['Alice appointment', 'pending', 1], ['Bob appointment', 'pending', 2], ['Confirmed appointment', 'confirmed', 1], ['Past appointment', 'pending', -1], ['Later appointment', 'pending', 7]] as [$name, $status, $days]) {
    Record::create(['kind' => 'booking', 'name' => $name, 'status' => $status,
        'data' => ['date' => now()->addDays($days)->toDateString(), 'phone' => '+1555010'.str_pad((string) $days, 4, '0', STR_PAD_LEFT)]]);
}
foreach (array_merge(glob($path.'/inbox/*.json') ?: [], glob($path.'/calls/*.json') ?: []) as $file) {
    unlink($file);
}
