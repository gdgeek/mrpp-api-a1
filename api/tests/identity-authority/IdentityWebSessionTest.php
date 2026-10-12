<?php

namespace tests\identityAuthority;

use app\components\IdentityAuthority;
use app\components\IdentityAuthorityClient;
use app\components\IdentityWebUser;
use app\modules\v1\models\User;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Yii;
use yii\web\HttpException;

final class IdentityWebSessionTest extends TestCase
{
    private mixed $originalApp;
    private mixed $mode;
    private array $cookies;
    private MemorySession $session;
    private IdentityAuthorityClient $client;
    private array $calls = [];
    private bool $revoked = false;
    private mixed $afterRefresh = null;

    protected function setUp(): void
    {
        $this->originalApp = Yii::$app;
        $this->mode = getenv('IDENTITY_DATA_SOURCE');
        $this->cookies = $_COOKIE;
        $_COOKIE = [];
        putenv('IDENTITY_DATA_SOURCE=identity');
        $this->client = new IdentityAuthorityClient('identity', 'http://identity.invalid', 'auth', 'iam', 1000, true,
            function ($path, $payload) {
                $this->calls[] = [$path, $payload];
                if ($path === '/internal/auth/verify-access-token') {
                    if ($payload['token'] === 'unavailable') throw new RuntimeException('unavailable');
                    if ($this->revoked || $payload['token'] === 'expired') return ['status' => 401, 'body' => []];
                    return ['status' => 200, 'body' => ['data' => $this->subject()]];
                }
                if ($path === '/v1/auth/logout') return ['status' => 200, 'body' => []];
                if ($path === '/internal/yii3/refresh') {
                    if ($payload['refreshToken'] === 'revoked') return ['status' => 401, 'body' => []];
                    if ($this->afterRefresh !== null) ($this->afterRefresh)();
                    return ['status' => 200, 'body' => ['subject' => $this->subject(), 'authTokens' => $this->tokens('rotated')]];
                }
                return ['status' => 200, 'body' => ['token' => $this->tokens('access')]];
            });
        new \yii\web\Application(['id' => 'a1-session-test', 'basePath' => dirname(__DIR__, 2), 'components' => [
            'session' => $this->session = new MemorySession(),
            'identityAuthority' => new IdentityAuthority(['client' => $this->client]),
            'request' => ['class' => \yii\web\Request::class, 'cookieValidationKey' => 'test-secret', 'hostInfo' => 'http://localhost', 'scriptUrl' => ''],
        ]]);
    }

    protected function tearDown(): void
    {
        $_COOKIE = $this->cookies;
        putenv($this->mode === false ? 'IDENTITY_DATA_SOURCE' : 'IDENTITY_DATA_SOURCE=' . $this->mode);
        Yii::$app = $this->originalApp;
    }

    public function testSignedCookieBridgesOnceWithoutRenewingItsBrowserExpiry(): void
    {
        $_COOKIE['_identity'] = Yii::$app->security->hashData(serialize(['_identity', json_encode([42, 'original-auth-key', 3600])]), 'test-secret');
        self::assertSame(42, $this->user()->getIdentity()->id);
        self::assertSame(['/internal/legacy-php-session/exchange', ['userId' => 42, 'authKey' => 'original-auth-key']], $this->calls[0]);
        self::assertFalse(Yii::$app->response->cookies->has('_identity'));
        self::assertSame(42, $this->user()->getIdentity()->id);
        self::assertCount(1, array_filter($this->calls, static fn($call) => $call[0] === '/internal/legacy-php-session/exchange'));
    }

    public function testUnsignedCookieNeverBridges(): void
    {
        $_COOKIE['_identity'] = json_encode([42, 'original-auth-key', 3600]);
        self::assertNull($this->user()->getIdentity());
        self::assertSame([], $this->calls);
    }

    public function testSessionWithOnlyUserIdNeverBridges(): void
    {
        $this->session->values['__id'] = 42;
        self::assertNull($this->user()->getIdentity());
        self::assertSame([], $this->calls);
        $this->session->values['__authKey'] = 'original-auth-key';
        self::assertSame(42, $this->user()->getIdentity()->id);
    }

    public function testExpiryRotatesWithIdentityAndRevocationClearsSession(): void
    {
        $this->session->values['__identity_backend_tokens'] = ['accessToken' => 'expired', 'refreshToken' => 'refresh'];
        self::assertSame(42, $this->user()->getIdentity()->id);
        self::assertSame('rotated', $this->session->values['__identity_backend_tokens']['accessToken']);
        self::assertSame(['refreshToken' => 'refresh', 'allowLoginCode' => false, 'consumer' => 'a1'], $this->calls[1][1]);
        $this->session->values['__identity_backend_tokens'] = ['accessToken' => 'expired', 'refreshToken' => 'revoked'];
        self::assertNull($this->user()->getIdentity());
        self::assertArrayNotHasKey('__identity_backend_tokens', $this->session->values);
    }

    public function testOutageKeepsTokensAndNeverRefreshesOrUsesLocalIdentity(): void
    {
        $tokens = ['accessToken' => 'unavailable', 'refreshToken' => 'refresh'];
        $this->session->values['__identity_backend_tokens'] = $tokens;
        try { $this->user()->getIdentity(); self::fail('Authority must fail closed.'); }
        catch (HttpException $exception) { self::assertSame(503, $exception->statusCode); }
        self::assertSame($tokens, $this->session->values['__identity_backend_tokens']);
        self::assertCount(1, $this->calls);
    }

    public function testLogoutNeverExchangesCookieAndCannotReviveInFlightRotation(): void
    {
        $this->session->values['__identity_backend_tokens'] = ['accessToken' => 'expired', 'refreshToken' => 'refresh'];
        $this->afterRefresh = function () { $this->user()->logout(false); $this->revoked = true; };
        self::assertNull($this->user()->getIdentity());
        self::assertArrayNotHasKey('__identity_backend_tokens', $this->session->values);
        self::assertCount(1, array_filter($this->calls, static fn($call) => $call[0] === '/v1/auth/logout'));
        self::assertCount(0, array_filter($this->calls, static fn($call) => $call[0] === '/internal/legacy-php-session/exchange'));
    }

    public function testRememberLoginStoresTokensOnlyInServerSession(): void
    {
        self::assertTrue($this->user()->loginWithIdentityTokens($this->tokens('access'), 3600));
        self::assertFalse(Yii::$app->response->cookies->has('_identity'));
        $cookie = Yii::$app->response->cookies->get($this->session->getName());
        self::assertNotNull($cookie);
        self::assertStringNotContainsString('refresh', $cookie->value);
        self::assertSame('refresh', $this->session->values['__identity_backend_tokens']['refreshToken']);
    }

    private function user(): IdentityWebUser
    {
        return new IdentityWebUser(['identityClass' => User::class, 'identityClient' => $this->client, 'enableAutoLogin' => true]);
    }
    private function subject(): array { return ['id' => 42, 'username' => 'user', 'nickname' => 'Current', 'status' => 10, 'roles' => ['user']]; }
    private function tokens(string $access): array { return ['accessToken' => $access, 'refreshToken' => 'refresh', 'expires' => '2026-10-12T12:00:00.000Z']; }
}

class MemorySession extends \yii\web\Session
{
    public array $values = [];
    private array $params = ['lifetime' => 0, 'path' => '/', 'domain' => '', 'secure' => false, 'httponly' => true];
    public function init() {}
    public function getCookieParams() { return $this->params; }
    public function setCookieParams($value) { $this->params = $value; }
    public function get($key, $default = null) { return $this->values[$key] ?? $default; }
    public function set($key, $value) { $this->values[$key] = $value; }
    public function remove($key) { $value = $this->values[$key] ?? null; unset($this->values[$key]); return $value; }
    public function regenerateID($deleteOldSession = false) {}
}
