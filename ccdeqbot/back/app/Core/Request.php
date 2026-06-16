<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    private static ?array $json = null;

    public static function json(): array
    {
        if (self::$json !== null) {
            return self::$json;
        }

        $raw = file_get_contents('php://input') ?: '';
        if ($raw === '') {
            return self::$json = [];
        }

        $decoded = json_decode($raw, true);
        return self::$json = is_array($decoded) ? $decoded : [];
    }

    public static function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return isset($_SERVER[$key]) ? trim((string) $_SERVER[$key]) : null;
    }

    public static function ip(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    }

    public static function userAgent(): string
    {
        return mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }
}
