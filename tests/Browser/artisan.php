<?php

declare(strict_types=1);
use Symfony\Component\Console\Input\ArgvInput;

$app = require __DIR__.'/bootstrap.php';
exit($app->handleCommand(new ArgvInput));
