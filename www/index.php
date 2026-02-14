<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$bootstrap = new App\Bootstrap;
$bootstrap->boot()
    ->createContainer()
    ->getByType(Nette\Application\Application::class)
    ->run();