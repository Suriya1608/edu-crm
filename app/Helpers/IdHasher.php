<?php

namespace App\Helpers;

class IdHasher
{
    private static function key(): string
    {
        return substr(hash('sha256', config('app.key'), true), 0, 4);
    }

    public static function encode(int $id): string
    {
        $xored = pack('N', $id) ^ self::key();
        return rtrim(strtr(base64_encode($xored), '+/', '-_'), '=');
    }

    public static function decode(string $hash): ?int
    {
        try {
            $padded  = str_pad(strtr($hash, '-_', '+/'), strlen($hash) + (4 - strlen($hash) % 4) % 4, '=');
            $decoded = base64_decode($padded, true);
            if ($decoded === false || strlen($decoded) !== 4) {
                return null;
            }
            ['id' => $id] = unpack('Nid', $decoded ^ self::key());
            return $id > 0 ? $id : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
