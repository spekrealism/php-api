<?php
declare(strict_types=1);

// Minimalistic router

final class Router
{
    /**
     * Match path like '/tasks' or '/tasks/{id}'.
     * Returns ['collection'] or ['item', id]
     */
    public static function matchTasks(string $method, string $uriPath): array
    {
        $path = Http::normalizePath(parse_url($uriPath, PHP_URL_PATH) ?? '/');

        if ($path === '/tasks') {
            return ['collection'];
        }

        // Match /tasks/{id} where id is positive integer
        if (preg_match('#^/tasks/(\\d+)$#', $path, $m)) {
            $id = (int)$m[1];
            return ['item', $id];
        }

        return ['nomatch'];
    }
}


