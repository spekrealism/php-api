<?php
declare(strict_types=1);

// Simple validation helpers for request payloads

final class Validation
{
    /**
     * Validate title: non-empty string, trimmed, length 1..200
     */
    public static function validateTitle(mixed $value): string
    {
        if (!is_string($value)) {
            Http::sendError('VALIDATION_ERROR', 'title must be a string', 400);
        }
        $title = trim($value);
        if ($title === '') {
            Http::sendError('VALIDATION_ERROR', 'title is required', 400);
        }
        if (mb_strlen($title) > 200) {
            Http::sendError('VALIDATION_ERROR', 'title length must be ≤ 200', 400);
        }
        return $title;
    }

    /**
     * Validate completed: strictly boolean
     */
    public static function validateCompleted(mixed $value): bool
    {
        if (!is_bool($value)) {
            Http::sendError('VALIDATION_ERROR', 'completed must be boolean', 400);
        }
        return (bool)$value;
    }
}


