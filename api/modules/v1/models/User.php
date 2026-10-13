<?php

namespace app\modules\v1\models;


use yii\web\IdentityInterface;
use Yii;
use OpenApi\Annotations as OA;
use app\components\IdentityAuthority;
use app\components\LegacyArContinuityAdapter;
use app\components\DeviceSnContext;

/**
 * @OA\Schema(
 *     schema="User",
 *     title="用户",
 *     description="User model",
 *     @OA\Property(property="id", type="integer", description="ID"),
 *     @OA\Property(property="username", type="string", description="用户名"),
 *     @OA\Property(property="nickname", type="string", description="昵称"),
 *     @OA\Property(property="email", type="string", description="邮箱"),
 *     @OA\Property(property="created_at", type="integer", description="创建时间"),
 *     @OA\Property(property="status", type="integer", description="状态")
 * )
 */
class User extends \yii\db\ActiveRecord implements IdentityInterface
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'user';
    }
    public $password;
    /** Provenance of a verified bearer identity; never convert SN to a password session. */
    public array $legacyDeviceSession = [];

    public function attributes()
    {
        return IdentityAuthority::enabled()
            ? ['id', 'username', 'nickname', 'email', 'status', 'created_at', 'updated_at', 'roles']
            : parent::attributes();
    }

    public static function fromIdentitySubject(array $subject): self
    {
        $user = new static();
        $user->setAttributes(array_intersect_key($subject, array_flip($user->attributes())), false);
        return $user;
    }

    public function save($runValidation = true, $attributeNames = null)
    {
        IdentityAuthority::forbidLocalCredentials();
        return parent::save($runValidation, $attributeNames);
    }

    public function insert($runValidation = true, $attributes = null)
    {
        IdentityAuthority::forbidLocalCredentials();
        return parent::insert($runValidation, $attributes);
    }

    public function update($runValidation = true, $attributeNames = null)
    {
        IdentityAuthority::forbidLocalCredentials();
        return parent::update($runValidation, $attributeNames);
    }

    public function beforeSave($insert)
    {
        IdentityAuthority::forbidLocalCredentials();
        return parent::beforeSave($insert);
    }

    public function beforeDelete()
    {
        IdentityAuthority::forbidLocalCredentials();
        return parent::beforeDelete();
    }

    public static function updateAll($attributes, $condition = '', $params = [])
    {
        IdentityAuthority::forbidLocalCredentials();
        return parent::updateAll($attributes, $condition, $params);
    }

    public static function updateAllCounters($counters, $condition = '', $params = [])
    {
        IdentityAuthority::forbidLocalCredentials();
        return parent::updateAllCounters($counters, $condition, $params);
    }

    public static function deleteAll($condition = null, $params = [])
    {
        IdentityAuthority::forbidLocalCredentials();
        return parent::deleteAll($condition, $params);
    }
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        $rules = [
            [['created_at', 'updated_at'], 'integer'],
            [['username', 'password_hash', 'password_reset_token',  'nickname'], 'string', 'max' => 255],
            [['auth_key'], 'string', 'max' => 32],
            [['username'], 'required'],
            [['username', 'password_reset_token'], 'unique'],
            [['password'], 'string', 'min' => 6, 'max' => 20, 'message' => 'Password must be between 6 and 20 characters.'],
            ['password', 'match', 'pattern' => '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{6,}$/i', 'message' => 'Password must contain at least one lowercase letter, one uppercase letter, one digit, and one special character.'],
          
        ];
        return $rules;
    }
    public function getId()
    {
        return $this->id;
    }
    public function getAuthKey()
    {
        if (IdentityAuthority::enabled()) return '';
        return $this->auth_key;
    }
    public function validateAuthKey($authKey)
    {
        if (IdentityAuthority::enabled()) return false;
        return $this->authKey === $authKey;
    }
   
    public function getUserLinkeds()
    {
        return $this->hasMany(UserLinked::className(), ['user_id' => 'id']);
    }
     public static function findByUserLinked($key):User|null
    {
        IdentityAuthority::forbidLocalCredentials();
        $link = UserLinked::find()->where(['key' => $key])->one();
        if (!$link) {
            throw new \yii\web\UnauthorizedHttpException('Linked key is invalid.');
        }
        
        $user = static::findIdentity($link->user_id);
        if (!$user) {
            throw new \yii\web\UnauthorizedHttpException('User is not found.');
        }
        return $user;
        
    }
    
     /**
     * Finds user by refresh token
     *
     * @param string $refreshToken
     * @return static|null
     * @throws \yii\web\UnauthorizedHttpException if refresh token is invalid or user is not found
     */

    public static function findByRefreshToken($refreshToken): User|null
    {
        IdentityAuthority::forbidLocalCredentials();
     

        $token = RefreshToken::find()->where(['key' => $refreshToken])->one();

        if (!$token) {
            throw new \yii\web\UnauthorizedHttpException('Refresh token is invalid.');
        }
        $user = static::findIdentity($token->user_id);
        if (!$user) {
            throw new \yii\web\UnauthorizedHttpException('User is not found.');
        }
        return $user;
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',//id 保留
            'username' => 'Username',//用户名 保留
            'auth_key' => 'Auth Key',//授权 key 必须
            'password_hash' => 'Password Hash',//密码 保留
            'password_reset_token' => 'Password Reset Token',// 修改密码用的 token 考虑
            //  'status' => 'Status',//状态 可选
            'created_at' => 'Created At',//创建时间 保留
            'updated_at' => 'Updated At',//更新时间 保留
            //  'verification_token' => 'Verification Token',//不保留
            //  'access_token' => 'Access Token',// 保留
            //'wx_openid' => 'Wx Openid',//微信openid 下次取消
            'nickname' => 'Nickname',//昵称 保留
        ];
    }


    public function token()
    {
        IdentityAuthority::forbidLocalCredentials();
        if (LegacyArContinuityAdapter::enabled()) {
            return IdentityAuthority::run(fn() => LegacyArContinuityAdapter::issue($this));
        }
        
        $token = new RefreshToken();
        $token->user_id = $this->id;

        $token->key = Yii::$app->security->generateRandomString();
        $token->save();
        $now = new \DateTimeImmutable('now', new \DateTimeZone(\Yii::$app->timeZone));
        $expires = $now->modify('+3 hour');
        return [
            'accessToken' => $this->generateAccessToken($now, $expires),
            'expires' => $expires->format('Y-m-d H:i:s'),
            'refreshToken' => $token->key,
        ];
    }
    public static function findByUsername($username)
    {
        IdentityAuthority::forbidLocalCredentials();
        return static::find()->where(['username' => $username])->one();

    }

     /**
     * Validates password
     *
     * @param string $password password to validate
     * @return bool if password provided is valid for current user
     */
    public function validatePassword($password)
    {
        IdentityAuthority::forbidLocalCredentials();
        return Yii::$app->security->validatePassword($password, $this->password_hash);
    }

    //生成token
    public function generateAccessToken($now = null, $expires = null)
    {
        IdentityAuthority::forbidLocalCredentials();

        if ($now == null) {
            $now = new \DateTimeImmutable('now', new \DateTimeZone(\Yii::$app->timeZone));
        }
        if ($expires == null) {
            $expires = $now->modify('+3 hour');
        }
        $token = Yii::$app->jwt->getBuilder()
            ->issuedBy(Yii::$app->request->hostInfo)
            ->issuedAt($now) // Configures the time that the token was issue (iat claim)
            ->canOnlyBeUsedAfter($now)
            ->expiresAt($expires) // Configures the expiration time of the token (exp claim)
            ->withClaim('uid', $this->id) // Configures a new claim, called "uid"
            ->getToken(
                Yii::$app->jwt->getConfiguration()->signer(),
                Yii::$app->jwt->getConfiguration()->signingKey()
            );
        return (string) $token->toString();
    }

    public static function findIdentity($id)
    {
        if (IdentityAuthority::enabled()) {
            $id = filter_var($id, FILTER_VALIDATE_INT);
            if ($id === false || $id <= 0) return null;
            $subject = IdentityAuthority::run(fn() => IdentityAuthority::provider()->subjectById($id));
            return static::fromIdentitySubject($subject);
        }
        return static::find()->where(['id' => $id])->one();
    }
    public static function findIdentityByAccessToken($token, $type = null)
    {
        if (IdentityAuthority::enabled()) return IdentityAuthority::verify((string)$token);
        $claims = Yii::$app->jwt->parse($token)->claims();
        $uid = $claims->get('uid');
        $user = static::findIdentity($uid);
        if ($user && LegacyArContinuityAdapter::enabled()) {
            $user->legacyDeviceSession = DeviceSnContext::fromArray($claims->all());
        }
        return $user;
    }

}
