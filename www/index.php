<?php declare(strict_types=1);

// Load Composer autoloader
require __DIR__ . '/../vendor/autoload.php';

// Enable Tracy debugger
Tracy\Debugger::enable(Tracy\Debugger::Development, __DIR__ . '/../log');

// Bootstrap application
$bootstrap = new App\Bootstrap;
$bootstrap->boot()
    ->createContainer()
    ->getByType(Nette\Application\Application::class)
    ->run();