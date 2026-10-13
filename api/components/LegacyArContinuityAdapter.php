<?php
declare(strict_types=1);

namespace app\components;

use app\modules\v1\models\User;
use RuntimeException;
use Yii;

/** A1 adapter. Shared engine performs no SQL and never owns this account lock. */
final class LegacyArContinuityAdapter
{
    public static function enabled(): bool
    {
        $value = getenv('AR_REFRESH_CONTINUITY_ENABLED');
        if ($value === false || $value === '') return false;
        $enabled = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($enabled === null) throw new RuntimeException('Legacy refresh configuration is invalid.',503);
        if ($enabled && IdentityAuthority::enabled()) throw new RuntimeException('Legacy refresh writers are retired.',503);
        return $enabled;
    }

    public static function engine(): LegacyArRefreshContinuity
    {
        $redis = Yii::$app->redis;
        if ($redis->retries !== 0) throw new RuntimeException('Legacy refresh Redis retries must be disabled.',503);
        $encoded = getenv('AR_REFRESH_REPLAY_KEY_BASE64');
        $key = is_string($encoded) ? base64_decode($encoded,true) : false;
        if ($key === false || strlen($key) !== 32 || base64_encode($key) !== $encoded) {
            throw new RuntimeException('Legacy refresh stable key is not configured.',503);
        }
        return new LegacyArRefreshContinuity(static fn($command,$args)=>$redis->executeCommand($command,$args),$key,'a1');
    }

    public static function issue(User $expected): array
    {
        if ($expected->legacyDeviceSession !== []) throw new RuntimeException('Device session cannot create ordinary credentials.',401);
        return self::locked((int)$expected->id, static function(User $current,array $account)use($expected):array {
            // The password/key login decision must remain valid after acquiring its lock.
            if ($current->password_hash !== $expected->password_hash || $current->auth_key !== $expected->auth_key) {
                throw new RuntimeException('Account credentials changed.',401);
            }
            return self::engine()->issue($account,static fn($raw,$provenance,$now)=>self::token($current,$raw,$provenance,$now));
        });
    }

    public static function refresh(string $raw, string $endpoint): array
    {
        if (!in_array($endpoint,['/v1/auth/refresh','/v1/common/report'],true)) throw new RuntimeException('Invalid refresh endpoint.',503);
        $engine=self::engine();
        $userId=$engine->credentialUserId($raw);
        $key=Yii::$app->request->headers->get('Idempotency-Key');
        if ($key!==null && !is_string($key)) throw new RuntimeException('Invalid idempotency key.',400);
        return self::locked($userId,static function(User $user,array $account)use($engine,$raw,$endpoint,$key):array {
            return $engine->refresh($raw,$account,$endpoint,$key,static function($successor,$provenance,$now)use($user,$endpoint):array {
                $token=self::token($user,$successor,$provenance,$now);
                if($endpoint==='/v1/common/report') return ['success'=>true,'message'=>'report success',
                    'user'=>['nickname'=>$user->nickname,'token'=>$token],'data'=>['watermark'=>false]];
                return ['success'=>true,'message'=>'refresh','nickname'=>$user->nickname,'token'=>$token];
            },['issuer'=>Yii::$app->request->hostInfo]);
        });
    }

    public static function logout(string $raw): array
    {
        $engine=self::engine();
        $userId=$engine->credentialUserId($raw);
        return self::locked($userId,static function(User $user,array $account)use($engine,$raw):array {
            $engine->revoke($raw,$account);
            return ['success'=>true];
        });
    }

    /** Account SQL changes are covered by reviewed SQL triggers, not model hooks. */
    private static function locked(int $userId, callable $operation): array
    {
        if(!self::enabled()) throw new RuntimeException('Legacy continuity is disabled.',503);
        if($userId<=0) throw new RuntimeException('Invalid account.',401);
        $db=Yii::$app->db;
        if($db->driverName!=='mysql') throw new RuntimeException('Legacy continuity requires reviewed MySQL row locks.',503);
        $existing=$db->getTransaction();
        $owned=$existing===null || !$existing->isActive;
        $transaction=$owned ? $db->beginTransaction(\yii\db\Transaction::READ_COMMITTED) : $existing;
        try {
            $row=$db->createCommand('SELECT * FROM `user` WHERE id=:id FOR UPDATE',[':id'=>$userId])->queryOne();
            if(!$row) throw new RuntimeException('Account is unavailable.',401);
            $epoch=$db->createCommand('SELECT CAST(epoch AS CHAR) FROM identity_legacy_security_versions WHERE user_id=:id FOR SHARE',[':id'=>$userId])->queryScalar();
            if(!is_string($epoch) || !preg_match('/^[1-9][0-9]{0,19}$/D',$epoch)
                || (strlen($epoch)===20 && strcmp($epoch,'18446744073709551615')>0)) throw new RuntimeException('Account security version is not prepared.',503);
            $current=new User(); User::populateRecord($current,$row);
            $account=['id'=>(int)$row['id'],'status'=>(int)$row['status'],
                'passwordHash'=>$row['password_hash']===null?null:(string)$row['password_hash'],
                'authKey'=>$row['auth_key']===null?null:(string)$row['auth_key'],'accountEpoch'=>$epoch];
            $result=$operation($current,$account);
            if($owned) $transaction->commit();
            return $result;
        } catch(\Throwable $e) {
            if($owned && $transaction->isActive) $transaction->rollBack();
            throw $e;
        }
    }

    private static function token(User $user,string $raw,array $provenance,int $redisTimeMs): array
    {
        // A1 has no legacy device-authorization source: never strip a PHP SN pair.
        if(DeviceSnContext::fromArray($provenance)!==[]) throw new RuntimeException('Device source is unavailable.',401);
        $now=(new \DateTimeImmutable('@'.intdiv($redisTimeMs,1000)))->setTimezone(new \DateTimeZone(Yii::$app->timeZone));
        $expires=$now->modify('+3 hours');
        return ['accessToken'=>$user->generateAccessToken($now,$expires),'expires'=>$expires->format('Y-m-d H:i:s'),'refreshToken'=>$raw];
    }
}
