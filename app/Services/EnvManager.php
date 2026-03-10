<?php

namespace App\Services;

class EnvManager
{
    /**
     * Update or insert key-value pairs in the .env file.
     *
     * @param  array<string,string>  $data  Keys are env variable names (case-insensitive).
     */
    public static function update(array $data): bool
    {
        $envPath = base_path('.env');

        if (!file_exists($envPath) || !is_writable($envPath)) {
            return false;
        }

        $content = file_get_contents($envPath);

        foreach ($data as $key => $value) {
            $key   = strtoupper(trim($key));
            $value = static::escapeValue((string) $value);

            if (preg_match('/^' . preg_quote($key, '/') . '=/m', $content)) {
                // Replace existing line
                $content = preg_replace(
                    '/^' . preg_quote($key, '/') . '=.*/m',
                    $key . '=' . $value,
                    $content
                );
            } else {
                // Append new key at end of file
                $content = rtrim($content) . "\n" . $key . '=' . $value . "\n";
            }
        }

        return file_put_contents($envPath, $content) !== false;
    }

    private static function escapeValue(string $value): string
    {
        if ($value === '') {
            return '""';
        }

        // Wrap in double quotes if value contains spaces, # or special characters
        if (preg_match('/[\s#"\'\\\\]/', $value)) {
            return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
        }

        return $value;
    }
}
