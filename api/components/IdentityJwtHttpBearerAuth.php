<?php

namespace app\components;

use yii\web\UnauthorizedHttpException;

/** Legacy JWT validation stays unchanged until the explicit authority switch. */
class IdentityJwtHttpBearerAuth extends \bizley\jwt\JwtHttpBearerAuth
{
    public function authenticate($user, $request, $response): ?\yii\web\IdentityInterface
    {
        if (!IdentityAuthority::enabled()) return parent::authenticate($user, $request, $response);
        $header = $request->headers->get('Authorization');
        if ($header === null) return null;
        if (!is_string($header) || strlen($header) > 16400 || preg_match('/^Bearer\s+(\S+)$/iD', $header, $match) !== 1) {
            throw new UnauthorizedHttpException('Authentication credentials are invalid.');
        }
        $identity = IdentityAuthority::verify($match[1]);
        if ($identity === null) throw new UnauthorizedHttpException('Authentication credentials are invalid.');
        $user->setIdentity($identity);
        return $identity;
    }
}
