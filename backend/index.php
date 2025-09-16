<?php
declare(strict_types=1);

require __DIR__ . '/src/Http.php';
require __DIR__ . '/src/Validation.php';
require __DIR__ . '/src/Router.php';
require __DIR__ . '/src/TaskRepository.php';

// Configuration: absolute path to storage file
$storagePath = __DIR__ . '/storage/tasks.json';

Http::handlePreflightIfNeeded();
Http::sendCorsHeaders();

// All responses are JSON
header('Content-Type: application/json; charset=utf-8');

// Basic routing
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = $_SERVER['REQUEST_URI'] ?? '/';

[$route, $id] = (static function () use ($method, $uri): array {
    $match = Router::matchTasks($method, $uri);
    if ($match[0] === 'collection') {
        return ['collection', null];
    }
    if ($match[0] === 'item') {
        return ['item', (int)$match[1]];
    }
    return ['nomatch', null];
})();

$repo = new TaskRepository($storagePath);

try {
    if ($route === 'collection') {
        if ($method === 'GET') {
            $tasks = $repo->getAll();
            Http::sendJson($tasks, 200);
        } elseif ($method === 'POST') {
            $body = Http::readJsonBody();
            if (!array_key_exists('title', $body)) {
                Http::sendError('VALIDATION_ERROR', 'title is required', 400);
            }
            $title = Validation::validateTitle($body['title']);
            $task = $repo->create($title);
            Http::sendJson($task, 201);
        } else {
            Http::sendError('NOT_FOUND', 'Route not found', 404);
        }
    } elseif ($route === 'item' && $id !== null) {
        if ($method === 'PATCH') {
            $body = Http::readJsonBody();
            $changes = [];
            if (array_key_exists('title', $body)) {
                $changes['title'] = Validation::validateTitle($body['title']);
            }
            if (array_key_exists('completed', $body)) {
                $changes['completed'] = Validation::validateCompleted($body['completed']);
            }
            if ($changes === []) {
                Http::sendError('VALIDATION_ERROR', 'No valid fields to update', 400);
            }
            $updated = $repo->update($id, $changes);
            Http::sendJson($updated, 200);
        } elseif ($method === 'DELETE') {
            $repo->delete($id);
            // 204 No Content
            Http::sendCorsHeaders();
            http_response_code(204);
            exit;
        } else {
            Http::sendError('NOT_FOUND', 'Route not found', 404);
        }
    } else {
        Http::sendError('NOT_FOUND', 'Route not found', 404);
    }
} catch (Throwable $e) {
    Http::sendError('INTERNAL_ERROR', 'Internal server error', 500);
}


