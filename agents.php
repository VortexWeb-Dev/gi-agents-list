<?php

declare(strict_types=1);

spl_autoload_register(function ($class) {
    require __DIR__ . "/src/{$class}.php";
});

$controller = new AgentsController();
$controller->processRequest($_SERVER['REQUEST_METHOD']);

exit;