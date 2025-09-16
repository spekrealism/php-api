<?php
declare(strict_types=1);

// Simple HTTP helper utilities

final class Http
{
    /**
     * Send common CORS headers.
     */
    public static function sendCorsHeaders(): void
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        header('Access-Control-Max-Age: 86400');
    }

    /**
     * Handle OPTIONS preflight and exit.
     */
    public static function handlePreflightIfNeeded(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
            self::sendCorsHeaders();
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(204);
            exit;
        }
    }

    /**
     * Send JSON response and exit.
     */
    public static function sendJson(mixed $data, int $status = 200, array $extraHeaders = []): void
    {
        self::sendCorsHeaders();
        header('Content-Type: application/json; charset=utf-8');
        foreach ($extraHeaders as $h) {
            header($h);
        }
        http_response_code($status);
        // Encode with unescaped unicode for readability
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            // Fallback in case of encoding error
            $json = '{"error":{"code":"ENCODE_ERROR","message":"Failed to encode JSON"}}';
            http_response_code(500);
        }
        echo $json;
        exit;
    }

    /**
     * Send error in unified format.
     */
    public static function sendError(string $code, string $message, int $status): void
    {
        self::sendJson(['error' => ['code' => $code, 'message' => $message]], $status);
    }

    /**
     * Read and decode JSON body with basic limits and validation.
     *
     * @return array<string,mixed>
     */
    public static function readJsonBody(int $maxBytes = 16384, bool $requireJsonContentType = true): array
    {
        // Enforce Content-Type: application/json (with optional charset)
        $contentType = $_SERVER['HTTP_CONTENT_TYPE'] ?? $_SERVER['CONTENT_TYPE'] ?? '';
        if ($requireJsonContentType) {
            if ($contentType === '' || stripos($contentType, 'application/json') !== 0) {
                self::sendError('UNSUPPORTED_MEDIA_TYPE', 'Content-Type must be application/json', 400);
            }
        }

        // Read raw input with size limit
        $input = fopen('php://input', 'rb');
        if ($input === false) {
            self::sendError('INPUT_ERROR', 'Unable to read request body', 500);
        }
        $data = stream_get_contents($input, $maxBytes + 1);
        fclose($input);
        if ($data === false) {
            self::sendError('INPUT_ERROR', 'Unable to read request body', 500);
        }
        if (strlen($data) === 0) {
            return [];
        }
        if (strlen($data) > $maxBytes) {
            self::sendError('PAYLOAD_TOO_LARGE', 'Request body too large', 400);
        }
        // Basic UTF-8 validation
        if (!mb_check_encoding($data, 'UTF-8')) {
            self::sendError('BAD_ENCODING', 'Body must be valid UTF-8 text', 400);
        }

        $decoded = json_decode($data, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            self::sendError('BAD_JSON', 'Invalid JSON', 400);
        }
        if (!is_array($decoded)) {
            self::sendError('BAD_JSON', 'JSON must be an object', 400);
        }

        /** @var array<string,mixed> $decoded */
        return $decoded;
    }

    /**
     * Normalize path like '/tasks/' => '/tasks', preserve root '/'.
     */
    public static function normalizePath(string $uriPath): string
    {
        $path = rtrim($uriPath, '/');
        return $path === '' ? '/' : $path;
    }
}


