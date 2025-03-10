<?php

declare(strict_types=1);

spl_autoload_register(function ($class) {
    require __DIR__ . "/src/{$class}.php";
});

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$parts = explode("/", $uri);
$parts = array_filter($parts);
$parts = array_values($parts);

$agentIndex = array_search('agents', $parts);

if ($agentIndex !== false) {
    $route = 'agents';

    $controller = new AgentsController();
    $controller->processRequest($_SERVER['REQUEST_METHOD']);
} else {
    header("Content-Type: application/json");
    http_response_code(404);
    echo json_encode(["error" => "Resource not found"]);
    exit;
}

exit;
