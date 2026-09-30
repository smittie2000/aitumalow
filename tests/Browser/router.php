<?php

declare(strict_types=1);
use Illuminate\Http\Request;

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (! is_string($uri)) {
    http_response_code(400);
    exit;
}
$dist = dirname(__DIR__, 2).'/ui/dist';
if (str_starts_with($uri, '/assets/')) {
    $file = realpath($dist.$uri);
    if ($file === false || ! str_starts_with($file, $dist.'/assets/')) {
        http_response_code(404);
        exit;
    }
    header('Content-Type: '.(str_ends_with($file, '.css') ? 'text/css' : 'application/javascript'));
    readfile($file);
    exit;
}
if (! str_starts_with($uri, '/workflow-engine') && ! str_starts_with($uri, '/testing/')) {
    header('Content-Type: text/html');
    readfile($dist.'/index.html');
    exit;
}
$app = require __DIR__.'/bootstrap.php';
$app->handleRequest(Request::capture());
