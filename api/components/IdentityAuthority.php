<?php

namespace app\components;

use app\modules\v1\models\User;
use RuntimeException;
use Yii;
use yii\base\BootstrapInterface;
use yii\base\Component;
use yii\web\HttpException;
use yii\web\BadRequestHttpException;
use yii\web\UnauthorizedHttpException;

/** The ownership switch never falls back to local credentials or sessions. */
class IdentityAuthority extends Component implements BootstrapInterface
{
    public ?IdentityAuthorityClient $client = null;

    public function bootstrap($app): void
    {
        // Include every CompositeAuth site, including v2 and fully qualified uses.
        Yii::$container->set(\bizley\jwt\JwtHttpBearerAuth::class, IdentityJwtHttpBearerAuth::class);
        if (self::enabled()) self::provider();
        if (LegacyArContinuityAdapter::enabled()) self::run(static fn()=>LegacyArContinuityAdapter::engine());
    }

    public static function enabled(): bool
    {
        $mode = getenv('IDENTITY_DATA_SOURCE');
        $mode = $mode === false ? 'legacy' : strtolower(trim($mode));
        if (!in_array($mode, ['legacy', 'identity'], true)) {
            throw new HttpException(503, 'Identity ownership configuration is invalid.');
        }
        return $mode === 'identity';
    }

    public static function provider(): IdentityAuthorityClient
    {
        $authority = Yii::$app->get('identityAuthority');
        if (!$authority instanceof self) throw new HttpException(503, 'Identity authority is not configured.');
        return $authority->client ??= self::run(static fn() => new IdentityAuthorityClient(
            self::enabled() ? 'identity' : 'legacy',
            self::env('IDENTITY_AUTH_BASE_URL'),
            self::env('IDENTITY_TOKEN_ISSUANCE_INTERNAL_API_TOKEN') ?: self::env('IDENTITY_ACCOUNT_INTERNAL_TOKEN') ?: self::env('IDENTITY_INTERNAL_API_TOKEN'),
            self::env('IDENTITY_IAM_INTERNAL_API_TOKEN') ?: self::env('IDENTITY_INTERNAL_API_TOKEN'),
            (int)(getenv('IDENTITY_AUTH_TIMEOUT_MS') ?: 1500),
            filter_var(getenv('IDENTITY_INDEPENDENT_READY') ?: 'false', FILTER_VALIDATE_BOOLEAN),
        ));
    }

    public static function run(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (RuntimeException $exception) {
            $status = in_array($exception->getCode(), [400, 401], true) ? $exception->getCode() : 503;
            if ($status === 400) throw new BadRequestHttpException('Authentication request is invalid.');
            if ($status === 401) throw new UnauthorizedHttpException('Authentication credentials are invalid.');
            throw new HttpException(503, 'Identity authority is unavailable.');
        }
    }

    public static function verify(string $token): ?User
    {
        $subject = self::run(static fn() => self::provider()->verifyAccessToken($token));
        return $subject === null ? null : User::fromIdentitySubject($subject);
    }

    public static function requiredIdentity(string $token): User
    {
        return self::verify($token) ?? throw new UnauthorizedHttpException('Authentication credentials are invalid.');
    }

    public static function forbidLocalCredentials(): void
    {
        if (self::enabled()) throw new HttpException(503, 'Local identity credentials are retired.');
    }

    public static function requestContext(): array
    {
        return ['idempotencyKey' => Yii::$app->request->headers->get('Idempotency-Key'),
            'userAgent' => Yii::$app->request->userAgent];
    }

    public static function credential(mixed $value, string $name, int $max = 4096): string
    {
        if (!is_string($value) || $value === '' || strlen($value) > $max) {
            throw new BadRequestHttpException($name . ' is invalid.');
        }
        return $value;
    }

    public static function response(array $result, string $message, bool $includeUser = true): array
    {
        $subject = $result['subject'];
        $body = ['success' => true, 'message' => $message, 'nickname' => $subject['nickname'], 'token' => $result['authTokens']];
        if ($includeUser) $body['user'] = User::fromIdentitySubject($subject)->toArray();
        return $body;
    }

    private static function env(string $name): string
    {
        return trim((string)(getenv($name) ?: ''));
    }
}
