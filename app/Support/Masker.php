<?php

namespace App\Support;

/**
 * Masks sensitive values before they are exposed to the AI assistant, exports or logs.
 */
class Masker
{
    public static function maskAccount(?string $value): ?string
    {
        return self::keepEnds($value, 2, 2);
    }

    public static function maskPhone(?string $value): ?string
    {
        return self::keepEnds($value, 4, 3);
    }

    public static function maskEmail(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if (! str_contains($value, '@')) {
            return self::keepEnds($value, 1, 0);
        }

        [$local, $domain] = explode('@', $value, 2);

        return mb_substr($local, 0, 1).str_repeat('*', max(mb_strlen($local) - 1, 3)).'@'.$domain;
    }

    public static function maskIp(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if (str_contains($value, ':')) {
            $parts = explode(':', $value);

            return implode(':', array_slice($parts, 0, 2)).':*:*';
        }

        $parts = explode('.', $value);

        return count($parts) === 4 ? $parts[0].'.'.$parts[1].'.*.*' : self::keepEnds($value, 2, 0);
    }

    public static function maskName(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $words = preg_split('/\s+/u', trim($value)) ?: [];

        return implode(' ', array_map(fn (string $word) => mb_substr($word, 0, 1).'***', $words));
    }

    /**
     * Mask the given keys of an array (recursively); the masker is chosen from the key name.
     *
     * @param  array<string|int, mixed>  $data
     * @param  array<int, string>  $keys
     * @return array<string|int, mixed>
     */
    public static function maskArray(array $data, array $keys): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::maskArray($value, $keys);

                continue;
            }

            if (is_string($key) && in_array($key, $keys, true) && $value !== null) {
                $data[$key] = self::maskByKey($key, (string) $value);
            }
        }

        return $data;
    }

    private static function maskByKey(string $key, string $value): ?string
    {
        $key = strtolower($key);

        return match (true) {
            str_contains($key, 'email') => self::maskEmail($value),
            str_contains($key, 'phone') || str_contains($key, 'mobile') => self::maskPhone($value),
            str_contains($key, 'ip') && ! str_contains($key, 'description') => self::maskIp($value),
            str_contains($key, 'account') => self::maskAccount($value),
            str_contains($key, 'name') => self::maskName($value),
            default => self::keepEnds($value, 1, 1),
        };
    }

    private static function keepEnds(?string $value, int $head, int $tail): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $length = mb_strlen($value);

        if ($length <= $head + $tail) {
            return str_repeat('*', $length);
        }

        return mb_substr($value, 0, $head)
            .str_repeat('*', $length - $head - $tail)
            .($tail > 0 ? mb_substr($value, -$tail) : '');
    }
}
