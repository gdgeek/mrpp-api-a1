<?php
declare(strict_types=1);

use app\components\LegacyArContinuityAdapter as Adapter;
use app\components\LegacyArRefreshContinuity as Engine;
use app\modules\v1\controllers\AuthController;
use app\modules\v1\controllers\CommonController;
use app\modules\v1\models\User;
use PHPUnit\Framework\TestCase;

final class ContinuityFixtureRedis extends \yii\redis\Connection
{
    public $beforeEval;
    public function executeCommand($name,$params=[])
    {
        if(strtoupper($name)==='EVAL' && $this->beforeEval!==null) ($this->beforeEval)();
        return parent::executeCommand($name,$params);
    }
}

/** Real MySQL triggers/locks + Redis + original A1 controllers and signer. */
final class LegacyArContinuityAdapterTest extends TestCase
{
    private PDO $admin;
    private string $database;
    private array $saved=[];
    private mixed $originalApp;
    private string $host;
    private int $port;
    private string $redisHost;
    private int $redisPort;

    protected function setUp(): void
    {
        if(getenv('A1_CONTINUITY_MYSQL_TEST')!=='1') self::markTestSkipped('Explicit disposable MySQL/Redis fixture required.');
        $this->host=getenv('A1_CONTINUITY_MYSQL_HOST')?:'127.0.0.1';
        $this->port=(int)(getenv('A1_CONTINUITY_MYSQL_PORT')?:13316);
        $this->redisHost=getenv('A1_CONTINUITY_REDIS_HOST')?:'127.0.0.1';
        $this->redisPort=(int)(getenv('A1_CONTINUITY_REDIS_PORT')?:16439);
        if(!in_array($this->host,['127.0.0.1','host.docker.internal'],true) || !in_array($this->port,[13316,3306],true)
            || !in_array($this->redisHost,['127.0.0.1','host.docker.internal','redis'],true) || !in_array($this->redisPort,[16439,6379],true)) throw new RuntimeException('Unsafe fixture address.');
        foreach(['IDENTITY_DATA_SOURCE','AR_REFRESH_CONTINUITY_ENABLED','AR_REFRESH_REPLAY_KEY_BASE64'] as $name) $this->saved[$name]=getenv($name);
        putenv('IDENTITY_DATA_SOURCE=legacy'); putenv('AR_REFRESH_CONTINUITY_ENABLED=true');
        putenv('AR_REFRESH_REPLAY_KEY_BASE64='.base64_encode(str_repeat('fixture-',4)));
        $this->originalApp=Yii::$app;
        $this->admin=new PDO("mysql:host={$this->host};port={$this->port}",getenv('A1_CONTINUITY_MYSQL_USER')?:'root',getenv('A1_CONTINUITY_MYSQL_PASSWORD')?:'');
        $this->admin->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        $this->database='a1_ar_' . bin2hex(random_bytes(6)) . '_test';
        $this->admin->exec('CREATE DATABASE `'.$this->database.'` CHARACTER SET utf8mb4');
        $db=new \yii\db\Connection(['dsn'=>"mysql:host={$this->host};port={$this->port};dbname={$this->database}",
            'username'=>getenv('A1_CONTINUITY_MYSQL_USER')?:'root','password'=>getenv('A1_CONTINUITY_MYSQL_PASSWORD')?:'','charset'=>'utf8mb4']);
        $db->open();
        $db->createCommand('CREATE TABLE user(id INT PRIMARY KEY,username VARCHAR(255) NOT NULL,nickname VARCHAR(255),email VARCHAR(255),password_hash VARCHAR(255),auth_key VARCHAR(32),password_reset_token VARCHAR(255),status INT NOT NULL,created_at INT,updated_at INT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci')->execute();
        $db->createCommand('CREATE TABLE identity_legacy_security_versions(user_id INT PRIMARY KEY,epoch BIGINT UNSIGNED NOT NULL,updated_at DATETIME(3) NOT NULL) ENGINE=InnoDB')->execute();
        foreach(['INSERT','UPDATE','DELETE'] as $operation) {
            $who=$operation==='DELETE'?'OLD':'NEW';
            $condition=$operation==='UPDATE' ? 'IF NOT (CAST(OLD.password_hash AS BINARY) <=> CAST(NEW.password_hash AS BINARY)) OR NOT (CAST(OLD.auth_key AS BINARY) <=> CAST(NEW.auth_key AS BINARY)) OR NOT (OLD.status <=> NEW.status) THEN ' : '';
            $end=$operation==='UPDATE'?' END IF;':'';
            $sql="CREATE TRIGGER fixture_security_".strtolower($operation)." AFTER $operation ON user FOR EACH ROW BEGIN $condition INSERT INTO identity_legacy_security_versions(user_id,epoch,updated_at) VALUES($who.id,1,UTC_TIMESTAMP(3)) ON DUPLICATE KEY UPDATE epoch=epoch+1,updated_at=UTC_TIMESTAMP(3); $end END";
            $db->createCommand($sql)->execute();
        }
        $db->createCommand()->insert('user',['id'=>41,'username'=>'fixture-user','nickname'=>'Original中文','email'=>'fixture@example.invalid',
            'password_hash'=>password_hash('fixture-password',PASSWORD_BCRYPT),'auth_key'=>'fixture-auth','status'=>10,'created_at'=>1,'updated_at'=>1])->execute();
        new \yii\web\Application($this->config($db));
        Yii::$app->redis->executeCommand('FLUSHDB');
        Yii::$app->redis->executeCommand('HSET',[Engine::PREFIX.'ready','v','1','protocol',Engine::VERSION,'consumers','a1,server','reviewSha256',str_repeat('a',64),'state','ready']);
    }

    private function config(mixed $db): array
    {
        return ['id'=>'a1-ar-fixture','basePath'=>dirname(__DIR__,2),'timeZone'=>'Asia/Shanghai',
            'modules'=>['v1'=>['class'=>\app\modules\v1\Module::class]],
            'components'=>['db'=>$db,'redis'=>['class'=>ContinuityFixtureRedis::class,'hostname'=>$this->redisHost,'port'=>$this->redisPort,'database'=>15],
                'request'=>['class'=>\yii\web\Request::class,'hostInfo'=>'https://a1.fixture.invalid','scriptUrl'=>'','cookieValidationKey'=>'fixture-only'],
                'user'=>['class'=>\yii\web\User::class,'identityClass'=>User::class,'enableSession'=>false],
                'jwt'=>['class'=>\bizley\jwt\Jwt::class,'signer'=>\bizley\jwt\Jwt::HS256,
                    'signingKey'=>['key'=>str_repeat('test-jwt',4),'method'=>\bizley\jwt\Jwt::METHOD_PLAIN]]]];
    }

    protected function tearDown(): void
    {
        if(isset($this->database)) {
            if(Yii::$app && Yii::$app->has('redis')) { Yii::$app->redis->beforeEval=null; Yii::$app->redis->executeCommand('FLUSHDB'); Yii::$app->redis->close(); }
            if(Yii::$app && Yii::$app->has('db')) Yii::$app->db->close();
            $this->admin->exec('DROP DATABASE `'.$this->database.'`');
            Yii::$app=$this->originalApp;
            foreach($this->saved as $name=>$value) putenv($value===false?$name:$name.'='.$value);
        }
    }

    private function refresh(string $raw,string $key='same'): array
    {
        Yii::$app->request->setBodyParams(['refreshToken'=>$raw]);
        Yii::$app->request->headers->set('Idempotency-Key',$key);
        return (new AuthController('auth',Yii::$app->getModule('v1')))->actionRefresh();
    }
    private function user(): User { return User::findOne(41); }
    private function epoch(): string { return (string)Yii::$app->db->createCommand('SELECT CAST(epoch AS CHAR) FROM identity_legacy_security_versions WHERE user_id=41')->queryScalar(); }
    private function rejects(callable $call,int $status): void
    {
        try{$call();self::fail('Must reject.');}catch(\yii\web\HttpException $e){self::assertSame($status,$e->statusCode,$e->getMessage());}
        catch(RuntimeException $e){self::assertSame($status,$e->getCode(),$e->getMessage());}
    }

    public function testControllersPreserveExactWholeResponseAndReportIssuesOnce(): void
    {
        $p=$this->user()->token()['refreshToken'];
        $first=$this->refresh($p);
        Yii::$app->db->createCommand("UPDATE user SET nickname='Changed' WHERE id=41")->execute();
        self::assertSame($first,$this->refresh($p));
        self::assertSame('1',$this->epoch(),'Profile-only changes do not revoke sessions.');
        self::assertSame(['accessToken','expires','refreshToken'],array_keys($first['token']));
        $claims=Yii::$app->jwt->parse($first['token']['accessToken'])->claims();
        self::assertSame(41,$claims->get('uid')); self::assertSame('https://a1.fixture.invalid',$claims->get('iss'));
        $before=(int)Yii::$app->redis->executeCommand('LLEN',['refresh_token']);
        Yii::$app->request->setBodyParams(['refreshToken'=>$p]);
        $report=(new CommonController('common',Yii::$app->getModule('v1')))->actionReport();
        self::assertSame($before+1,(int)Yii::$app->redis->executeCommand('LLEN',['refresh_token']));
        self::assertSame($report,(new CommonController('common',Yii::$app->getModule('v1')))->actionReport());
        self::assertSame('report success',$report['message']);
    }

    public function testRawPasswordAndStatusABATriggersBlockOriginalCache(): void
    {
        $p=$this->user()->token()['refreshToken']; $this->refresh($p);
        $original=$this->user()->password_hash;
        Yii::$app->db->createCommand('UPDATE user SET password_hash=:h WHERE id=41',[':h'=>'changed'])->execute();
        Yii::$app->db->createCommand('UPDATE user SET password_hash=:h WHERE id=41',[':h'=>$original])->execute();
        self::assertSame('3',$this->epoch()); $this->rejects(fn()=>$this->refresh($p),401);
        $q=$this->user()->token()['refreshToken']; $this->refresh($q);
        User::updateAll(['status'=>0],['id'=>41]); User::updateAll(['status'=>10],['id'=>41]);
        self::assertSame('5',$this->epoch()); $this->rejects(fn()=>$this->refresh($q),401);
    }

    public function testDeleteAndReinsertSameIdNeverRevivesOldCredential(): void
    {
        $p=$this->user()->token()['refreshToken']; $row=$this->user()->getAttributes(); $this->refresh($p);
        User::deleteAll(['id'=>41]); Yii::$app->db->createCommand()->insert('user',$row)->execute();
        self::assertSame('3',$this->epoch()); $this->rejects(fn()=>$this->refresh($p),401);
    }

    public function testByteExactAuthKeyABACannotHideBehindLegacyCollation(): void
    {
        $p=$this->user()->token()['refreshToken']; $this->refresh($p);
        foreach(['FIXTURE-AUTH','fixture-auth ','fixture-auth'] as $key) {
            Yii::$app->db->createCommand('UPDATE user SET auth_key=:key WHERE id=41',[':key'=>$key])->execute();
        }
        self::assertSame('4',$this->epoch()); $this->rejects(fn()=>$this->refresh($p),401);
    }

    public function testSharedLogoutRevokesPAndSuccessorBeforeAndAfterReplay(): void
    {
        $p=$this->user()->token()['refreshToken']; $s=$this->refresh($p)['token']['refreshToken'];
        Yii::$app->request->setBodyParams(['refreshToken'=>$s]);
        self::assertSame(['success'=>true],(new AuthController('auth',Yii::$app->getModule('v1')))->actionLogout());
        $this->rejects(fn()=>$this->refresh($p),401); $this->rejects(fn()=>$this->refresh($s),401);
    }

    public function testIssuanceKeepsSqlAccountRowLockedAcrossRedisEval(): void
    {
        $other=new PDO("mysql:host={$this->host};port={$this->port};dbname={$this->database}",getenv('A1_CONTINUITY_MYSQL_USER')?:'root',getenv('A1_CONTINUITY_MYSQL_PASSWORD')?:'');
        $other->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION); $other->exec('SET SESSION innodb_lock_wait_timeout=1');
        $blocked=false;
        Yii::$app->redis->beforeEval=function()use($other,&$blocked){
            try{$other->exec('UPDATE user SET status=0 WHERE id=41');self::fail('Row lock released before Redis write.');}
            catch(PDOException $e){self::assertSame(1205,(int)$e->errorInfo[1]);$blocked=true;}
        };
        $this->user()->token(); self::assertTrue($blocked); self::assertSame(10,(int)$this->user()->status);
        Yii::$app->redis->beforeEval=null;
    }

    public function testStaleLoginDecisionAndMissingLedgerFailClosedWithoutCredential(): void
    {
        $user=$this->user(); Yii::$app->db->createCommand("UPDATE user SET password_hash='changed' WHERE id=41")->execute();
        $this->rejects(fn()=>$user->token(),401);
        Yii::$app->db->createCommand('DELETE FROM identity_legacy_security_versions WHERE user_id=41')->execute();
        $this->rejects(fn()=>$this->user()->token(),503);
        self::assertSame(0,(int)Yii::$app->redis->executeCommand('LLEN',['refresh_token']));
    }

    public function testUnsignedSqlEpochIsPreservedAsStringAndSourceSnCannotMintPlainToken(): void
    {
        Yii::$app->db->createCommand("UPDATE identity_legacy_security_versions SET epoch='18446744073709551615' WHERE user_id=41")->execute();
        $p=$this->user()->token()['refreshToken']; self::assertTrue($this->refresh($p)['success']);
        $meta=Yii::$app->redis->executeCommand('HGET',[Engine::PREFIX.'byHash:'.hash('sha256',$p),'accountEpoch']);
        self::assertSame('18446744073709551615',$meta);
        $user=$this->user(); $user->legacyDeviceSession=['auth_method'=>'device_sn','device_sn_id'=>7];
        $this->rejects(fn()=>$user->token(),401);
    }

    public function testFeatureDefaultOffHasOriginalPermanentArAndNoMetadata(): void
    {
        putenv('AR_REFRESH_CONTINUITY_ENABLED'); putenv('AR_REFRESH_REPLAY_KEY_BASE64');
        $token=$this->user()->token(); self::assertIsString($token['refreshToken']);
        self::assertSame([],Yii::$app->redis->executeCommand('KEYS',[Engine::PREFIX.'byHash:*']));
        self::assertSame(1,(int)Yii::$app->redis->executeCommand('LLEN',['refresh_token']));
        self::assertSame(-1,(int)Yii::$app->redis->executeCommand('PTTL',['refresh_token:a:1']));
    }

    public function testStableKeyConfigurationIsMandatoryAndIdentityCannotRunLegacyWriter(): void
    {
        putenv('AR_REFRESH_REPLAY_KEY_BASE64'); $this->rejects(fn()=>$this->user()->token(),503);
        putenv('AR_REFRESH_REPLAY_KEY_BASE64='.base64_encode(str_repeat('fixture-',4)));
        putenv('IDENTITY_DATA_SOURCE=identity'); $this->rejects(fn()=>Adapter::enabled(),503);
    }

    public function testRedisClientRetriesAreRejectedBeforeAnyCredentialWrite(): void
    {
        Yii::$app->redis->retries=1;
        $this->rejects(fn()=>$this->user()->token(),503);
        self::assertSame(0,(int)Yii::$app->redis->executeCommand('LLEN',['refresh_token']));
        self::assertSame([],Yii::$app->redis->executeCommand('KEYS',[Engine::PREFIX.'byHash:*']));
        Yii::$app->redis->retries=0;
        self::assertIsString($this->user()->token()['refreshToken']);
    }

    public function testRealLoopbackHttpRetryReturnsExactOriginalA1Body(): void
    {
        $p=$this->user()->token()['refreshToken'];
        $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);
        if($socket===false) throw new RuntimeException('Fixture listener unavailable.');
        $address=stream_socket_get_name($socket,false); fclose($socket);
        $directory=sys_get_temp_dir().'/a1-ar-http-'.bin2hex(random_bytes(6)); mkdir($directory,0700);
        $config=$this->config(['class'=>\yii\db\Connection::class,'dsn'=>"mysql:host={$this->host};port={$this->port};dbname={$this->database}",
            'username'=>getenv('A1_CONTINUITY_MYSQL_USER')?:'root','password'=>getenv('A1_CONTINUITY_MYSQL_PASSWORD')?:'','charset'=>'utf8mb4']);
        $config['components']['redis']['class']=\yii\redis\Connection::class;
        $config['components']['request']['parsers']=['application/json'=>\yii\web\JsonParser::class];
        $source='<?php require '.var_export(dirname(__DIR__).'/public-filter-bootstrap.php',true).'; $app=new \\yii\\web\\Application('.var_export($config,true).');'
            .' try { $result=$app->runAction(ltrim(parse_url($_SERVER["REQUEST_URI"],PHP_URL_PATH),"/")); }'
            .' catch(\\yii\\web\\HttpException $e){$app->response->statusCode=$e->statusCode;$result=["error"=>"rejected"];} '
            .'$app->response->format=\\yii\\web\\Response::FORMAT_JSON;$app->response->data=$result;$app->response->send();';
        $file=$directory.'/router.php'; file_put_contents($file,$source); chmod($file,0600);
        $process=proc_open([PHP_BINARY,'-S',$address,$file],[0=>['file','/dev/null','r'],1=>['file','/dev/null','w'],2=>['file','/dev/null','w']],$pipes,$directory);
        try {
            if(!is_resource($process)) throw new RuntimeException('Fixture process unavailable.');
            for($i=0;$i<100;$i++) { $ready=@stream_socket_client('tcp://'.$address,$errno,$error,0.05); if(is_resource($ready)){fclose($ready);break;} usleep(10000); }
            $context=stream_context_create(['http'=>['method'=>'POST','header'=>"Content-Type: application/json\r\nIdempotency-Key: real-http-retry\r\n",
                'content'=>json_encode(['refreshToken'=>$p]),'ignore_errors'=>true,'timeout'=>3]]);
            $url='http://'.$address.'/v1/auth/refresh';
            $first=file_get_contents($url,false,$context); self::assertIsString($first);
            Yii::$app->db->createCommand("UPDATE user SET nickname='after HTTP' WHERE id=41")->execute();
            $second=file_get_contents($url,false,$context); self::assertSame($first,$second);
            $body=json_decode($first,true,64,JSON_THROW_ON_ERROR);
            self::assertTrue($body['success']); self::assertSame('Original中文',$body['nickname']);
            self::assertNotSame($p,$body['token']['refreshToken']);
            self::assertSame(2,(int)Yii::$app->redis->executeCommand('LLEN',['refresh_token']));
        } finally {
            if(is_resource($process)){proc_terminate($process);proc_close($process);} @unlink($file);@rmdir($directory);
        }
    }
}
