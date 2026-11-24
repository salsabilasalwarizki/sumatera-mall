<?php

namespace App\Support;

final class RdfImageUrl
{
    public static function fromBinding(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $parts = parse_url($value);

        if ($parts === false) {
            return null;
        }

        $path = $parts['path'] ?? '';

        if (preg_match('~\A/images/malls/[A-Za-z0-9._-]+\z~D', $path)) {
            return asset(ltrim($path, '/'));
        }

        if (! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])) {
            return null;
        }

        return $value;
    }
}
