<?php
declare(strict_types=1);

use app\components\LegacyArRefreshContinuity as Engine;
use PHPUnit\Framework\TestCase;

/** Isolated Redis only; the runner supplies a disposable instance, never a live DB. */
final class LegacyArRefreshContinuityTest extends TestCase
{
    private \yii\redis\Connection $redis;
    private array $account;

    protected function setUp(): void
    {
        if (getenv('A1_CONTINUITY_REDIS_TEST') !== '1') {
            self::markTestSkipped('Explicit disposable Redis fixture required.');
        }
        $host = getenv('A1_CONTINUITY_REDIS_HOST') ?: '127.0.0.1';
        $port = (int)(getenv('A1_CONTINUITY_REDIS_PORT') ?: 16439);
        if (!in_array($host, ['127.0.0.1','host.docker.internal','redis'], true)
            || !in_array($port, [16439,6379], true)) throw new RuntimeException('Unsafe test Redis.');
        $this->redis = new \yii\redis\Connection(['hostname'=>$host,'port'=>$port,'database'=>15]);
        $this->redis->open();
        $this->redis->executeCommand('FLUSHDB');
        $this->hash(Engine::PREFIX . 'ready', ['v'=>'1','protocol'=>Engine::VERSION,'consumers'=>'a1,server',
            'reviewSha256'=>str_repeat('a',64),'state'=>'ready']);
        $this->account = ['id'=>41,'status'=>10,'passwordHash'=>'fixture-bcrypt-hash','authKey'=>'fixture-auth', 'accountEpoch'=>'1'];
    }

    protected function tearDown(): void
    {
        if (isset($this->redis)) { $this->redis->executeCommand('FLUSHDB'); $this->redis->close(); }
    }

    private function engine(string $consumer = 'a1', ?callable $execute = null, ?callable $guard = null, string $keyByte = 'a'): Engine
    {
        return new Engine($execute ?? fn($c,$a) => $this->redis->executeCommand($c,$a), str_repeat($keyByte,32), $consumer, $guard);
    }

    private function builder(string $name = 'original'): callable
    {
        return static fn($raw,$provenance,$time) => ['success'=>true,'message'=>'refresh','nickname'=>$name,
            'token'=>['accessToken'=>'fixture-jwt-' . $raw,'refreshToken'=>$raw,'expires'=>'2030-01-02 03:04:05'],
            'nested'=>['null'=>null,'unicode'=>'中文','number'=>42],'provenance'=>$provenance];
    }

    private function issue(?Engine $engine = null): array { return ($engine ?? $this->engine())->issue($this->account, $this->builder()); }
    private function fields(?int $expiry = null): array
    {
        if ($expiry === null) return ['issuer'=>'https://a1.fixture.invalid'];
        return ['issuer'=>'https://server.fixture.invalid','keyEncoding'=>'sha256','expiresAtMs'=>$expiry,
            'created_at'=>(string)(intdiv($expiry,1000)-604800),'expires_at'=>(string)intdiv($expiry,1000),'session_id'=>'fixture-session'];
    }
    private function now(): int { $t=$this->redis->executeCommand('TIME'); return (int)$t[0]*1000 + intdiv((int)$t[1],1000); }
    private function hash(string $key, array $value): void
    {
        $args=[$key]; foreach($value as $k=>$v) { $args[]=$k; $args[]=(string)$v; }
        $this->redis->executeCommand('HSET',$args);
    }
    private function meta(string $raw): array
    {
        $flat=$this->redis->executeCommand('HGETALL',[Engine::PREFIX . 'byHash:' . hash('sha256',$raw)]);
        $result=[]; for($i=0;$i<count($flat);$i+=2) $result[$flat[$i]]=$flat[$i+1]; return $result;
    }
    private function rejects(callable $call, int $status): void
    {
        try { $call(); self::fail('Expected fail-closed rejection.'); }
        catch(RuntimeException $e) { self::assertSame($status,$e->getCode(),$e->getMessage()); }
    }

    public function testA1PermanentPredecessorAndWholeResponseReplay(): void
    {
        $engine=$this->engine(); $p=$this->issue($engine)['token']['refreshToken'];
        $original=$engine->refresh($p,$this->account,'/v1/auth/refresh','retry',$this->builder(),$this->fields());
        $replay=$engine->refresh($p,$this->account,'/v1/auth/refresh','retry',$this->builder('changed nickname'),$this->fields());
        self::assertSame($original,$replay);
        $other=$engine->refresh($p,$this->account,'/v1/auth/refresh','another',$this->builder(),$this->fields());
        self::assertNotSame($original['token']['refreshToken'],$other['token']['refreshToken']);
        $m=$this->meta($p);
        self::assertSame(-1,(int)$this->redis->executeCommand('PTTL',['refresh_token:a:' . $m['recordId']]));
        self::assertSame(3,(int)$this->redis->executeCommand('LLEN',['refresh_token']));
        self::assertSame('',$m['consumedBy']);
    }

    public function testA1ThenServerConsumeMakesA1CacheInvalid(): void
    {
        $a1=$this->engine(); $server=$this->engine('server',null,null,'s'); $p=$this->issue($a1)['token']['refreshToken'];
        $a1->refresh($p,$this->account,'/v1/auth/refresh','a',$this->builder(),$this->fields());
        $fields=$this->fields((intdiv($this->now(),1000)+604800)*1000);
        $body=$server->refresh($p,$this->account,'/v1/site/refresh','s',$this->builder('server'),$fields);
        self::assertSame('server',$this->meta($p)['consumedBy']);
        self::assertSame(0,(int)$this->redis->executeCommand('EXISTS',['refresh_token:a:' . $this->meta($p)['recordId']]));
        $this->rejects(fn()=>$a1->refresh($p,$this->account,'/v1/auth/refresh','a',$this->builder(),$this->fields()),401);
        self::assertSame($body,$server->refresh($p,$this->account,'/v1/site/refresh','s',$this->builder('new'),$fields));
        $this->rejects(fn()=>$server->refresh($p,$this->account,'/v1/site/refresh','different',$this->builder(),$fields),401);
    }

    public function testLogoutConsumedPredecessorRevokesServerSuccessorAndAllA1Children(): void
    {
        $a1=$this->engine(); $server=$this->engine('server',null,null,'s'); $p=$this->issue($a1)['token']['refreshToken'];
        $a=$a1->refresh($p,$this->account,'/v1/auth/refresh','a',$this->builder(),$this->fields())['token']['refreshToken'];
        $fields=$this->fields((intdiv($this->now(),1000)+604800)*1000);
        $s=$server->refresh($p,$this->account,'/v1/site/refresh','s',$this->builder(),$fields)['token']['refreshToken'];
        $server->revoke($p,$this->account);
        foreach([$p,$a,$s] as $raw) $this->rejects(fn()=>$a1->refresh($raw,$this->account,'/v1/auth/refresh',null,$this->builder(),$this->fields()),401);
        $this->rejects(fn()=>$server->refresh($p,$this->account,'/v1/site/refresh','s',$this->builder(),$fields),401);
    }

    public function testLogoutAnySuccessorRevokesPermanentPredecessor(): void
    {
        $a1=$this->engine(); $p=$this->issue($a1)['token']['refreshToken'];
        $s=$a1->refresh($p,$this->account,'/v1/auth/refresh',null,$this->builder(),$this->fields())['token']['refreshToken'];
        $a1->revoke($s,$this->account);
        $this->rejects(fn()=>$a1->refresh($p,$this->account,'/v1/auth/refresh',null,$this->builder(),$this->fields()),401);
    }

    public function testUserEpochAndSqlEpochPreventABA(): void
    {
        $a1=$this->engine(); $p=$this->issue($a1)['token']['refreshToken'];
        $a1->refresh($p,$this->account,'/v1/auth/refresh','same',$this->builder(),$this->fields());
        $changed=$this->account; $changed['accountEpoch']='3';
        $this->rejects(fn()=>$a1->refresh($p,$changed,'/v1/auth/refresh','same',$this->builder(),$this->fields()),401);
        $a1->revokeUser($this->account);
        $this->rejects(fn()=>$a1->refresh($p,$this->account,'/v1/auth/refresh','same',$this->builder(),$this->fields()),401);
    }

    public function testCurrentPasswordAndStatusBeatCache(): void
    {
        $a1=$this->engine(); $p=$this->issue($a1)['token']['refreshToken'];
        $a1->refresh($p,$this->account,'/v1/auth/refresh','same',$this->builder(),$this->fields());
        foreach(['passwordHash'=>'changed','status'=>0] as $field=>$value) {
            $changed=$this->account; $changed[$field]=$value;
            $this->rejects(fn()=>$a1->refresh($p,$changed,'/v1/auth/refresh','same',$this->builder(),$this->fields()),401);
        }
    }

    public function testCacheScopedByEndpointKeyAndIssuer(): void
    {
        $a1=$this->engine(); $p=$this->issue($a1)['token']['refreshToken'];
        $r=$a1->refresh($p,$this->account,'/v1/auth/refresh','key',$this->builder(),$this->fields());
        $other=$a1->refresh($p,$this->account,'/v1/common/report','key',$this->builder(),$this->fields());
        self::assertNotSame($r,$other);
        $this->rejects(fn()=>$a1->refresh($p,$this->account,'/v1/auth/refresh','key',$this->builder(),['issuer'=>'https://other.invalid']),503);
        $this->rejects(fn()=>$a1->refresh($p,$this->account,'/v1/auth/refresh',"bad\nkey",$this->builder(),$this->fields()),400);
    }

    public function testWrongKeyAndAadTamperingNeverIssueAgain(): void
    {
        $a1=$this->engine(); $p=$this->issue($a1)['token']['refreshToken'];
        $a1->refresh($p,$this->account,'/v1/auth/refresh','key',$this->builder(),$this->fields());
        $n=(int)$this->redis->executeCommand('LLEN',['refresh_token']);
        $wrong=$this->engine('a1',null,null,'w');
        $this->rejects(fn()=>$wrong->refresh($p,$this->account,'/v1/auth/refresh','key',$this->builder(),$this->fields()),503);
        $keys=$this->redis->executeCommand('KEYS',[Engine::PREFIX . 'cache:*']);
        $this->redis->executeCommand('HSET',[$keys[0],'createdAtMs',(string)($this->now()-1)]);
        $this->rejects(fn()=>$a1->refresh($p,$this->account,'/v1/auth/refresh','key',$this->builder(),$this->fields()),503);
        self::assertSame($n,(int)$this->redis->executeCommand('LLEN',['refresh_token']));
    }

    public function testUnknownOldRecordFails503WithoutDeletingPermanentCredential(): void
    {
        $raw='review-required'; $this->hash('refresh_token:a:9',['id'=>'9','user_id'=>'41','key'=>$raw]);
        $this->rejects(fn()=>$this->engine()->credentialUserId($raw),503);
        self::assertSame(1,(int)$this->redis->executeCommand('EXISTS',['refresh_token:a:9']));
        self::assertSame(-1,(int)$this->redis->executeCommand('PTTL',['refresh_token:a:9']));
    }

    public function testReviewedBackfillPreservesAllOldFieldsAndPermanentExpiry(): void
    {
        $raw='reviewed-plain'; $record=['id'=>'9','user_id'=>'41','key'=>$raw,'user_agent'=>'original-agent'];
        $this->hash('refresh_token:a:9',$record); $this->redis->executeCommand('SET',['refresh_token:s:id','9']);
        $before=$this->redis->executeCommand('HGETALL',['refresh_token:a:9']);
        $this->engine()->registerReviewedCredential($raw,$this->account,['recordId'=>'9','record'=>$record,
            'recordKeyEncoding'=>'plain','expiresAtMs'=>null,'reviewSha256'=>str_repeat('b',64)]);
        self::assertSame($before,$this->redis->executeCommand('HGETALL',['refresh_token:a:9']));
        self::assertSame(-1,(int)$this->redis->executeCommand('PTTL',['refresh_token:a:9']));
        $this->engine()->refresh($raw,$this->account,'/v1/auth/refresh','key',$this->builder(),$this->fields());
        self::assertSame(41,$this->engine()->credentialUserId($raw));
    }

    public function testReviewedHashedOldRowNeedsNoRawAndRefreshesWithOriginalBearer(): void
    {
        $raw='old-server-bearer'; $hash=hash('sha256',$raw);
        $expiry=(intdiv($this->now(),1000)+604800)*1000;
        $record=['id'=>'9','user_id'=>'41','key'=>$hash,'expires_at'=>(string)intdiv($expiry,1000),'user_agent'=>'preserved'];
        $this->hash('refresh_token:a:9',$record); $this->redis->executeCommand('SET',['refresh_token:s:id','9']);
        $before=$this->redis->executeCommand('HGETALL',['refresh_token:a:9']);
        $server=$this->engine('server',null,null,'s');
        $server->registerReviewedRecordHash($hash,$this->account,['recordId'=>'9','record'=>$record,
            'recordKeyEncoding'=>'sha256','expiresAtMs'=>$expiry,'reviewSha256'=>str_repeat('b',64)]);
        self::assertSame($before,$this->redis->executeCommand('HGETALL',['refresh_token:a:9']));
        self::assertSame(41,$server->credentialUserId($raw));
        $body=$server->refresh($raw,$this->account,'/v1/auth/refresh','retry',$this->builder(),$this->fields($expiry));
        self::assertSame($body,$server->refresh($raw,$this->account,'/v1/auth/refresh','retry',$this->builder('changed'),$this->fields($expiry)));
        self::assertSame('server',$this->meta($raw)['consumedBy']);
    }

    public function testReviewedHashRefusesWrongHashEncodingAndStaleWholeRow(): void
    {
        $hash=hash('sha256','old-server-bearer'); $record=['id'=>'9','user_id'=>'41','key'=>$hash];
        $this->hash('refresh_token:a:9',$record);
        $review=['recordId'=>'9','record'=>$record,'recordKeyEncoding'=>'sha256','expiresAtMs'=>null,'reviewSha256'=>str_repeat('b',64)];
        $engine=$this->engine('server');
        $this->rejects(fn()=>$engine->registerReviewedRecordHash(str_repeat('c',64),$this->account,$review),503);
        $wrong=$review; $wrong['recordKeyEncoding']='plain';
        $this->rejects(fn()=>$engine->registerReviewedRecordHash($hash,$this->account,$wrong),503);
        $this->rejects(fn()=>$engine->registerReviewedRecordHash(strtoupper($hash),$this->account,$review),503);
        $this->redis->executeCommand('HSET',['refresh_token:a:9','user_agent','changed-after-review']);
        $this->rejects(fn()=>$engine->registerReviewedRecordHash($hash,$this->account,$review),503);
        self::assertSame([], $this->redis->executeCommand('KEYS',[Engine::PREFIX.'byHash:*']));
        self::assertSame('changed-after-review',$this->redis->executeCommand('HGET',['refresh_token:a:9','user_agent']));
    }

    public function testReviewedHashedRowCasRejectsConcurrentMutationWithoutMetadata(): void
    {
        $hash=hash('sha256','old-server-bearer'); $record=['id'=>'9','user_id'=>'41','key'=>$hash];
        $this->hash('refresh_token:a:9',$record);
        $race=$this->engine('server',function($c,$a){
            if($c==='EVAL') $this->redis->executeCommand('HSET',['refresh_token:a:9','user_agent','concurrent']);
            return $this->redis->executeCommand($c,$a);
        });
        $this->rejects(fn()=>$race->registerReviewedRecordHash($hash,$this->account,['recordId'=>'9','record'=>$record,
            'recordKeyEncoding'=>'sha256','expiresAtMs'=>null,'reviewSha256'=>str_repeat('b',64)]),503);
        self::assertSame([], $this->redis->executeCommand('KEYS',[Engine::PREFIX.'byHash:*']));
        self::assertSame([], $this->redis->executeCommand('KEYS',[Engine::PREFIX.'family:*']));
        self::assertNull($this->redis->executeCommand('GET',[Engine::PREFIX.'user:41:epoch']));
    }

    public function testReplayChecksArRevocationAndDeletion(): void
    {
        $a1=$this->engine(); $p=$this->issue($a1)['token']['refreshToken'];
        $a1->refresh($p,$this->account,'/v1/auth/refresh','key',$this->builder(),$this->fields());
        $this->redis->executeCommand('HSET',['refresh_token:a:' . $this->meta($p)['recordId'],'revoked_at','1']);
        $this->rejects(fn()=>$a1->refresh($p,$this->account,'/v1/auth/refresh','key',$this->builder(),$this->fields()),401);
    }

    public function testLostEvalAckIsNotRetriedAndExactRequestRecoversStoredBody(): void
    {
        $a1=$this->engine(); $p=$this->issue($a1)['token']['refreshToken']; $calls=0;
        $lost=$this->engine('a1',function($c,$a)use(&$calls){ $r=$this->redis->executeCommand($c,$a); if($c==='EVAL'){ $calls++; throw new RuntimeException('lost acknowledgement'); } return $r; });
        $this->rejects(fn()=>$lost->refresh($p,$this->account,'/v1/auth/refresh','key',$this->builder(),$this->fields()),503);
        self::assertSame(1,$calls); self::assertSame(2,(int)$this->redis->executeCommand('LLEN',['refresh_token']));
        $body=$a1->refresh($p,$this->account,'/v1/auth/refresh','key',$this->builder('must not run'),$this->fields());
        self::assertSame('original',$body['nickname']);
    }

    public function testLuaCasRejectsConcurrentFamilyRevocationWithoutSuccessor(): void
    {
        $a1=$this->engine(); $p=$this->issue($a1)['token']['refreshToken']; $family=$this->meta($p)['familyId'];
        $race=$this->engine('a1',function($c,$a)use($family){ if($c==='EVAL')$this->redis->executeCommand('HSET',[Engine::PREFIX . 'family:' . $family,'revoked','1']); return $this->redis->executeCommand($c,$a); });
        $this->rejects(fn()=>$race->refresh($p,$this->account,'/v1/auth/refresh','key',$this->builder(),$this->fields()),503);
        self::assertSame(1,(int)$this->redis->executeCommand('LLEN',['refresh_token']));
    }

    public function testBuilderFailureAndWrongDestinationTypeLeaveNoSuccessor(): void
    {
        $a1=$this->engine(); $p=$this->issue($a1)['token']['refreshToken'];
        $this->rejects(fn()=>$a1->refresh($p,$this->account,'/v1/auth/refresh','key',fn()=>throw new RuntimeException('builder',503),$this->fields()),503);
        self::assertSame(1,(int)$this->redis->executeCommand('LLEN',['refresh_token']));
        $this->redis->executeCommand('DEL',['refresh_token']); $this->redis->executeCommand('SET',['refresh_token','wrong type']);
        $this->rejects(fn()=>$a1->refresh($p,$this->account,'/v1/auth/refresh','key',$this->builder(),$this->fields()),503);
        self::assertSame('1',$this->redis->executeCommand('GET',['refresh_token:s:id']));
    }

    public function testMissingSuccessorDoesNotDeletePermanentPOrPreventNewRedemption(): void
    {
        $a1=$this->engine(); $p=$this->issue($a1)['token']['refreshToken'];
        $s=$a1->refresh($p,$this->account,'/v1/auth/refresh','key',$this->builder(),$this->fields())['token']['refreshToken'];
        $this->redis->executeCommand('DEL',['refresh_token:a:' . $this->meta($s)['recordId']]);
        $this->rejects(fn()=>$a1->refresh($p,$this->account,'/v1/auth/refresh','key',$this->builder(),$this->fields()),401);
        $next=$a1->refresh($p,$this->account,'/v1/auth/refresh','new-key',$this->builder(),$this->fields());
        self::assertNotSame($s,$next['token']['refreshToken']);
        self::assertSame(1,(int)$this->redis->executeCommand('EXISTS',['refresh_token:a:' . $this->meta($p)['recordId']]));
    }

    public function testServerFiniteExpiryBoundsResponseCacheAndA1DoesNotExtendP(): void
    {
        $server=$this->engine('server',null,null,'s'); $expiry=(intdiv($this->now(),1000)+2)*1000;
        $p=$server->issue($this->account,$this->builder(),$this->fields($expiry))['token']['refreshToken'];
        $body=$server->refresh($p,$this->account,'/v1/site/refresh','key',$this->builder(),$this->fields($expiry));
        $keys=$this->redis->executeCommand('KEYS',[Engine::PREFIX . 'cache:*']);
        $ttl=(int)$this->redis->executeCommand('PTTL',[$keys[0]]); self::assertGreaterThan(0,$ttl); self::assertLessThanOrEqual(2000,$ttl);
        usleep(2100000);
        $this->rejects(fn()=>$server->refresh($p,$this->account,'/v1/site/refresh','key',$this->builder(),$this->fields($expiry)),401);
    }

    public function testDeviceProvenanceMustBeVerifiedAndNeverDowngraded(): void
    {
        $server=$this->engine('server',null,fn($uid,$p)=>$uid===41 && $p['device_sn_id']===7,'s');
        $fields=$this->fields((intdiv($this->now(),1000)+604800)*1000)+['auth_method'=>'device_sn','device_sn_id'=>'7'];
        $p=$server->issue($this->account,$this->builder(),$fields)['token']['refreshToken'];
        $this->rejects(fn()=>$this->engine()->refresh($p,$this->account,'/v1/auth/refresh','key',$this->builder(),$this->fields()),401);
        $this->rejects(fn()=>$server->refresh($p,$this->account,'/v1/site/refresh','key',$this->builder(),$this->fields($fields['expiresAtMs'])),401);
        $body=$server->refresh($p,$this->account,'/v1/site/refresh','key',$this->builder(),$fields);
        self::assertSame(['auth_method'=>'device_sn','device_sn_id'=>7],$body['provenance']);
    }

    public function testNoKeyFiveSecondsAndNoReplayExtension(): void
    {
        $a1=$this->engine(); $p=$this->issue($a1)['token']['refreshToken'];
        $body=$a1->refresh($p,$this->account,'/v1/auth/refresh',null,$this->builder(),$this->fields());
        $keys=$this->redis->executeCommand('KEYS',[Engine::PREFIX . 'cache:*']);
        $before=(int)$this->redis->executeCommand('PTTL',[$keys[0]]);
        usleep(20000);
        self::assertSame($body,$a1->refresh($p,$this->account,'/v1/auth/refresh',null,$this->builder('new'),$this->fields()));
        self::assertLessThan($before,(int)$this->redis->executeCommand('PTTL',[$keys[0]]));
        self::assertLessThanOrEqual(5000,$before);
    }

    public function testExplicitKeyWindowIsThirtySecondsAndMetadataNeverExpires(): void
    {
        $a1=$this->engine();$p=$this->issue($a1)['token']['refreshToken'];
        $a1->refresh($p,$this->account,'/v1/auth/refresh','key',$this->builder(),$this->fields());
        $keys=$this->redis->executeCommand('KEYS',[Engine::PREFIX.'cache:*']);
        $ttl=(int)$this->redis->executeCommand('PTTL',[$keys[0]]);
        self::assertGreaterThan(29000,$ttl); self::assertLessThanOrEqual(30000,$ttl);
        foreach($this->redis->executeCommand('KEYS',[Engine::PREFIX.'*']) as $key) {
            if(!str_starts_with($key,Engine::PREFIX.'cache:')) self::assertSame(-1,(int)$this->redis->executeCommand('PTTL',[$key]));
        }
    }

    public function testAfterNoKeyWindowA1CanRedeemSamePermanentPAgain(): void
    {
        $a1=$this->engine();$p=$this->issue($a1)['token']['refreshToken'];
        $first=$a1->refresh($p,$this->account,'/v1/auth/refresh',null,$this->builder(),$this->fields());
        usleep(5100000);
        $next=$a1->refresh($p,$this->account,'/v1/auth/refresh',null,$this->builder('next'),$this->fields());
        self::assertNotSame($first['token']['refreshToken'],$next['token']['refreshToken']);
        self::assertSame('next',$next['nickname']);
        self::assertSame(-1,(int)$this->redis->executeCommand('PTTL',['refresh_token:a:'.$this->meta($p)['recordId']]));
    }

    public function testA1DoesNotAcquireNewLookupSupportForServerHashedCredentials(): void
    {
        $server=$this->engine('server',null,null,'s');
        $p=$server->issue($this->account,$this->builder(),$this->fields((intdiv($this->now(),1000)+604800)*1000))['token']['refreshToken'];
        $this->rejects(fn()=>$this->engine()->refresh($p,$this->account,'/v1/auth/refresh',null,$this->builder(),$this->fields()),401);
        self::assertSame(1,(int)$this->redis->executeCommand('LLEN',['refresh_token']));
    }

    public function testPreparingBackfillDoesNotAllowOnlineOperations(): void
    {
        $this->redis->executeCommand('HSET',[Engine::PREFIX.'ready','state','preparing']);
        $record=['id'=>'9','user_id'=>'41','key'=>'fixture-reviewed'];$this->hash('refresh_token:a:9',$record);
        $this->engine()->registerReviewedCredential('fixture-reviewed',$this->account,['recordId'=>'9','record'=>$record,
            'recordKeyEncoding'=>'plain','expiresAtMs'=>null,'reviewSha256'=>str_repeat('b',64)]);
        $this->rejects(fn()=>$this->engine()->credentialUserId('fixture-reviewed'),503);
        $this->redis->executeCommand('HSET',[Engine::PREFIX.'ready','state','ready']);
        self::assertSame(41,$this->engine()->credentialUserId('fixture-reviewed'));
    }

    public function testPhysicalTtlAndArCounterCapacityRefuseWithoutDeletingP(): void
    {
        $a1=$this->engine();$p=$this->issue($a1)['token']['refreshToken'];
        $this->redis->executeCommand('PEXPIRE',[Engine::PREFIX.'user:41:epoch','10000']);
        $this->rejects(fn()=>$a1->refresh($p,$this->account,'/v1/auth/refresh',null,$this->builder(),$this->fields()),503);
        self::assertGreaterThan(0,(int)$this->redis->executeCommand('PTTL',[Engine::PREFIX.'user:41:epoch']));
        $this->redis->executeCommand('PERSIST',[Engine::PREFIX.'user:41:epoch']);
        $this->redis->executeCommand('SET',['refresh_token:s:id','2147483647']);
        $this->rejects(fn()=>$a1->refresh($p,$this->account,'/v1/auth/refresh',null,$this->builder(),$this->fields()),503);
        self::assertSame(1,(int)$this->redis->executeCommand('LLEN',['refresh_token']));
    }

    public function testCompleteArSnapshotMutationBetweenReadAndEvalDoesNotConsume(): void
    {
        $a1=$this->engine();$p=$this->issue($a1)['token']['refreshToken'];$id=$this->meta($p)['recordId'];
        $race=$this->engine('server',function($c,$a)use($id){if($c==='EVAL')$this->redis->executeCommand('HSET',['refresh_token:a:'.$id,'user_agent','concurrent-writer']);return $this->redis->executeCommand($c,$a);},null,'s');
        $this->rejects(fn()=>$race->refresh($p,$this->account,'/v1/site/refresh','key',$this->builder(),$this->fields((intdiv($this->now(),1000)+604800)*1000)),503);
        self::assertSame(1,(int)$this->redis->executeCommand('EXISTS',['refresh_token:a:'.$id]));
        self::assertSame('',$this->meta($p)['consumedBy']);
        self::assertSame(1,(int)$this->redis->executeCommand('LLEN',['refresh_token']));
    }
}
