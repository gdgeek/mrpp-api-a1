<?php

namespace tests\identityAuthority;

use app\components\IdentityAuthority;
use app\components\IdentityAuthorityClient;
use app\components\IdentityJwtHttpBearerAuth;
use app\components\IdentityWebUser;
use app\modules\v1\controllers\AuthController;
use app\modules\v1\controllers\CommonController;
use app\modules\v1\models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Yii;
use yii\web\HttpException;

final class IdentityAuthorityTest extends TestCase
{
    private mixed $originalApp;
    private mixed $originalMode;
    private mixed $originalMethod;
    private mixed $filterDefinition;
    private array $calls = [];
    private int $status = 200;
    private array $subject = ['id' => 42, 'username' => 'private-user', 'nickname' => 'Current',
        'email' => null, 'status' => 10, 'roles' => ['user'], 'password_hash' => 'must-never-be-returned'];
    private array $tokens = ['accessToken' => 'identity-access', 'refreshToken' => 'identity-refresh', 'expires' => '2026-10-12T12:00:00.000Z'];
    private IdentityAuthorityClient $client;

    protected function setUp(): void
    {
        $this->originalApp = Yii::$app;
        $this->originalMode = getenv('IDENTITY_DATA_SOURCE');
        $this->originalMethod = $_SERVER['REQUEST_METHOD'] ?? null;
        $this->filterDefinition = Yii::$container->getDefinitions()[\bizley\jwt\JwtHttpBearerAuth::class] ?? null;
        putenv('IDENTITY_DATA_SOURCE=identity');
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->client = new IdentityAuthorityClient('identity', 'http://identity.invalid', 'auth-key', 'iam-key', 1000, true,
            function ($path, $payload, $headers, $timeout, $method) {
                $this->calls[] = compact('path', 'payload', 'headers', 'timeout', 'method');
                if ($this->status !== 200) return ['status' => $this->status, 'body' => ['message' => 'private upstream error']];
                return ['status' => 200, 'body' => match ($path) {
                    '/v1/auth/login', '/internal/legacy-php-session/exchange' => ['token' => $this->tokens],
                    '/v1/auth/logout' => ['revoked' => true],
                    '/internal/auth/verify-access-token', '/internal/identity/subjects/42' => ['data' => $this->subject],
                    default => ['authTokens' => $this->tokens, 'subject' => $this->subject],
                }];
            });
        new \yii\web\Application(['id' => 'a1-identity-test', 'basePath' => dirname(__DIR__, 2),
            'components' => [
                'identityAuthority' => new IdentityAuthority(['client' => $this->client]),
                'request' => ['class' => \yii\web\Request::class, 'hostInfo' => 'http://localhost', 'scriptUrl' => '', 'cookieValidationKey' => 'test-only'],
                'user' => ['class' => IdentityWebUser::class, 'identityClass' => User::class, 'enableSession' => false],
                'db' => ['class' => ForbiddenLocalDependency::class],
                'redis' => ['class' => ForbiddenLocalDependency::class],
                'jwt' => ['class' => ForbiddenLocalDependency::class],
            ]]);
        ForbiddenLocalDependency::$opened = 0;
    }

    protected function tearDown(): void
    {
        self::assertSame(0, ForbiddenLocalDependency::$opened, 'Identity operations cannot open local account stores or signer.');
        putenv($this->originalMode === false ? 'IDENTITY_DATA_SOURCE' : 'IDENTITY_DATA_SOURCE=' . $this->originalMode);
        if ($this->originalMethod === null) unset($_SERVER['REQUEST_METHOD']);
        else $_SERVER['REQUEST_METHOD'] = $this->originalMethod;
        Yii::$app = $this->originalApp;
        if ($this->filterDefinition === null) Yii::$container->clear(\bizley\jwt\JwtHttpBearerAuth::class);
        else Yii::$container->set(\bizley\jwt\JwtHttpBearerAuth::class, $this->filterDefinition);
    }

    public function testDefaultOwnershipAndStaticTemplateAccountsRemainLegacyOnly(): void
    {
        putenv('IDENTITY_DATA_SOURCE=legacy');
        self::assertFalse(IdentityAuthority::enabled());
        self::assertFalse((new IdentityAuthorityClient())->enabled());
        self::assertSame('admin', \app\models\User::findByUsername('admin')->username);
        putenv('IDENTITY_DATA_SOURCE=identitty');
        $this->expectException(HttpException::class);
        IdentityAuthority::enabled();
    }

    public function testExplicitNativeModeRequiresCompleteReadiness(): void
    {
        foreach ([['identity', false], ['invalid', true]] as [$mode, $ready]) {
            try {
                new IdentityAuthorityClient($mode, 'http://identity.invalid', 'auth', 'iam', 1000, $ready);
                self::fail('Incomplete readiness must fail closed.');
            } catch (RuntimeException $error) { self::assertSame(503, $error->getCode()); }
        }
    }

    public function testBootstrapCoversFullyQualifiedV2BearerFilters(): void
    {
        Yii::$app->get('identityAuthority')->bootstrap(Yii::$app);
        $filter = Yii::createObject(\bizley\jwt\JwtHttpBearerAuth::class);
        self::assertInstanceOf(IdentityJwtHttpBearerAuth::class, $filter);
        Yii::$app->request->headers->set('Authorization', 'Bearer opaque-identity-access');
        self::assertSame(42, $filter->authenticate(Yii::$app->user, Yii::$app->request, Yii::$app->response)->id);
    }

    public function testLoginPreservesOldA1EnvelopeAndReturnsNoCredentials(): void
    {
        Yii::$app->request->setBodyParams(['username' => 'submitted', 'password' => 'submitted-password']);
        $result = $this->controller()->actionLogin();
        self::assertSame('login', $result['message']);
        self::assertSame('Current', $result['nickname']);
        self::assertSame(['accessToken' => 'identity-access', 'expires' => '2026-10-12 20:00:00', 'refreshToken' => 'identity-refresh'], $result['token']);
        self::assertSame(42, $result['user']['id']);
        self::assertStringNotContainsString('must-never', json_encode($result));
        self::assertSame(['username' => 'submitted', 'password' => 'submitted-password'], $this->calls[0]['payload']);
        self::assertSame('iam-key', $this->calls[1]['headers']['X-Identity-Internal-Token']);
    }

    public function testRefreshNeverTreatsARefreshFailureAsASecondLoginCodeWriter(): void
    {
        Yii::$app->request->setBodyParams(['refreshToken' => 'old-or-new-refresh', 'consumer' => 'server']);
        $result = $this->controller()->actionRefresh();
        self::assertSame('refresh', $result['message']);
        self::assertArrayNotHasKey('user', $result);
        self::assertSame('/internal/yii3/refresh', $this->calls[0]['path']);
        self::assertSame(['refreshToken' => 'old-or-new-refresh', 'allowLoginCode' => false, 'consumer' => 'a1'], $this->calls[0]['payload']);
        self::assertSame('auth-key', $this->calls[0]['headers']['X-Identity-Internal-Token']);
    }

    public function testEveryMultiDeviceCodeExchangeDelegatesWithoutConsumingLocalSqlKey(): void
    {
        Yii::$app->request->setBodyParams(['key' => 'web_existing-code']);
        for ($i = 0; $i < 2; ++$i) self::assertSame('keyToToken', $this->controller()->actionKeyToToken()['message']);
        self::assertCount(2, $this->calls);
        self::assertSame(['key' => 'web_existing-code', 'operation' => 'redeem'], $this->calls[0]['payload']);
        self::assertSame('/internal/yii3/login-code', $this->calls[1]['path']);
    }

    public function testCommonReportRotatesOnceAndPreservesItsOriginalWatermarkEnvelope(): void
    {
        Yii::$app->request->setBodyParams(['refreshToken' => 'existing-refresh']);
        $result = (new CommonController('common', Yii::$app))->actionReport();
        self::assertSame(['watermark' => false], $result['data']);
        self::assertSame('report success', $result['message']);
        self::assertSame('identity-refresh', $result['user']['token']['refreshToken']);
        self::assertCount(1, $this->calls);
    }

    #[DataProvider('failures')]
    public function testAuthorityFailureDoesNotFallBackOrDiscloseUpstreamDetails(int $status, int $expected): void
    {
        $this->status = $status;
        Yii::$app->request->setBodyParams(['refreshToken' => 'existing-refresh']);
        try { $this->controller()->actionRefresh(); self::fail('Authority failure must fail the request.'); }
        catch (HttpException $error) {
            self::assertSame($expected, $error->statusCode);
            self::assertStringNotContainsString('upstream', $error->getMessage());
        }
        self::assertCount(1, $this->calls);
    }

    public static function failures(): array
    {
        return [[400, 400], [401, 401], [403, 401], [404, 503], [503, 503]];
    }

    public function testBusinessAuthUsesLivePrivateSubjectWithoutTheOldHs256Key(): void
    {
        Yii::$app->request->headers->set('Authorization', 'Bearer opaque-old-or-new-token');
        $user = (new IdentityJwtHttpBearerAuth())->authenticate(Yii::$app->user, Yii::$app->request, Yii::$app->response);
        self::assertSame(42, $user->id);
        self::assertSame(['user'], $user->roles);
        self::assertSame('/internal/auth/verify-access-token', $this->calls[0]['path']);
        $this->subject['status'] = 0;
        $this->expectException(\yii\web\UnauthorizedHttpException::class);
        (new IdentityJwtHttpBearerAuth())->authenticate(Yii::$app->user, Yii::$app->request, Yii::$app->response);
    }

    public function testAuthorityOutageCannotAuthenticateAStaleBusinessUser(): void
    {
        $this->status = 503;
        Yii::$app->request->headers->set('Authorization', 'Bearer any-token');
        try { (new IdentityJwtHttpBearerAuth())->authenticate(Yii::$app->user, Yii::$app->request, Yii::$app->response); self::fail('Outage must fail.'); }
        catch (HttpException $error) { self::assertSame(503, $error->statusCode); }
        self::assertNull(Yii::$app->user->getIdentity(false));
    }

    public function testCurrentSubjectLookupDoesNotReadBusinessUserCredentials(): void
    {
        self::assertSame(42, User::findIdentity(42)->id);
        self::assertSame('/internal/identity/subjects/42', $this->calls[0]['path']);
        self::assertSame('GET', $this->calls[0]['method']);
    }

    #[DataProvider('localWriters')]
    public function testLocalCredentialAndStaticAccountBypassesAreRetired(string $operation): void
    {
        $this->expectException(HttpException::class);
        match ($operation) {
            'token' => (new User())->token(),
            'jwt' => (new User())->generateAccessToken(),
            'password' => (new User())->validatePassword('password'),
            'refresh-read' => User::findByRefreshToken('refresh'),
            'code-read' => User::findByUserLinked('key'),
            'user-save' => (new User())->beforeSave(true),
            'user-validate-save' => (new User())->save(),
            'user-insert' => (new User())->insert(),
            'user-update-instance' => (new User())->update(),
            'user-delete' => User::deleteAll(['id' => 42]),
            'user-update' => User::updateAll(['password_hash' => 'password'], ['id' => 42]),
            'redis-save' => (new \app\modules\v1\models\RefreshToken())->beforeSave(true),
            'redis-validate-save' => (new \app\modules\v1\models\RefreshToken())->save(),
            'redis-delete' => \app\modules\v1\models\RefreshToken::deleteAll(),
            'static-user' => \app\models\User::findByUsername('admin'),
            'static-password' => (new \app\models\User())->validatePassword('admin'),
            'linked-save' => (new \app\modules\v1\models\UserLinked())->save(),
            'linked-update' => \app\modules\v1\models\UserLinked::updateAll(['key' => 'new-key'], ['user_id' => 42]),
        };
    }

    public static function localWriters(): array
    {
        return array_map(static fn($value) => [$value], ['token', 'jwt', 'password', 'refresh-read', 'code-read',
            'user-save', 'user-validate-save', 'user-insert', 'user-update-instance', 'user-delete', 'user-update',
            'redis-save', 'redis-validate-save', 'redis-delete', 'static-user', 'static-password', 'linked-save', 'linked-update']);
    }

    public function testHeadersKeepSingleIdempotencyContextAndCannotInjectNewHeaders(): void
    {
        Yii::$app->request->headers->set('Idempotency-Key', 'one-request');
        Yii::$app->request->headers->set('User-Agent', 'Unity fixture');
        Yii::$app->request->setBodyParams(['refreshToken' => 'existing-refresh']);
        $this->controller()->actionRefresh();
        self::assertSame('one-request', $this->calls[0]['headers']['Idempotency-Key']);
        Yii::$app->request->headers->set('Idempotency-Key', "bad\r\nInjected: yes");
        $this->expectException(\yii\web\BadRequestHttpException::class);
        $this->controller()->actionRefresh();
    }

    public function testHtmlLoginUsesIdentityInsteadOfTheStaticDemoAccount(): void
    {
        $form = new \app\models\LoginForm(['username' => 'canonical-user', 'password' => 'password']);
        self::assertTrue($form->validate());
        self::assertSame(42, $form->getUser()->id);
        self::assertSame('/v1/auth/login', $this->calls[0]['path']);
    }

    private function controller(): AuthController
    {
        return new AuthController('auth', Yii::$app);
    }
}

class ForbiddenLocalDependency extends \yii\base\Component
{
    public static int $opened = 0;
    public function init()
    {
        ++self::$opened;
        throw new RuntimeException('Local identity storage or signer must not open.');
    }
}
