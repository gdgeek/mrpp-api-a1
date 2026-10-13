<?php
declare(strict_types=1);

namespace app\components;

use RuntimeException;

/** Validate the authenticated bridge's original A1 response, never public input. */
final class LegacyIdentityRefreshResponse
{
    public static function body(mixed $legacy, string $endpoint, array $tokens): array
    {
        self::keys($legacy, ['version', 'consumer', 'endpoint', 'body']);
        if ($legacy['version'] !== 'legacy-refresh-response-v1' || $legacy['consumer'] !== 'a1'
            || $legacy['endpoint'] !== $endpoint) self::fail();
        $body = $legacy['body'];
        if ($endpoint === '/v1/auth/refresh') {
            self::keys($body, ['success', 'message', 'nickname', 'token']);
            if ($body['success'] !== true || $body['message'] !== 'refresh') self::fail();
            self::nickname($body['nickname']);
            self::token($body['token'], $tokens);
        } elseif ($endpoint === '/v1/common/report') {
            self::keys($body, ['success', 'message', 'user', 'data']);
            self::keys($body['user'], ['nickname', 'token']);
            self::keys($body['data'], ['watermark']);
            if ($body['success'] !== true || $body['message'] !== 'report success' || $body['data']['watermark'] !== false) self::fail();
            self::nickname($body['user']['nickname']);
            self::token($body['user']['token'], $tokens);
        } else self::fail();
        if (strlen(json_encode($body, JSON_THROW_ON_ERROR)) > 65536) self::fail();
        return $body;
    }

    private static function token(mixed $body, array $tokens): void
    {
        self::keys($body, ['accessToken', 'refreshToken', 'expires']);
        if ($body['accessToken'] !== $tokens['accessToken'] || $body['refreshToken'] !== $tokens['refreshToken']
            || !is_string($body['expires']) || strlen($body['expires']) !== 19) self::fail();
        // The original display string is preserved. JWT exp, verified by
        // Identity, authorizes the session and supplies authTokens ISO expiry.
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $body['expires'], new \DateTimeZone('UTC'));
        if ($date === false || \DateTimeImmutable::getLastErrors() !== false || $date->format('Y-m-d H:i:s') !== $body['expires']) self::fail();
    }

    private static function nickname(mixed $value): void
    {
        if ($value !== null && (!is_string($value) || strlen($value) > 4096 || preg_match('//u', $value) !== 1)) self::fail();
    }

    private static function keys(mixed $value, array $expected): void
    {
        if (!is_array($value)) self::fail();
        $actual = array_keys($value); sort($actual); sort($expected);
        if ($actual !== $expected) self::fail();
    }

    private static function fail(): never { throw new RuntimeException('Identity returned invalid legacy response data.', 503); }
}
