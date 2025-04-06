<?php

namespace app\modules\v1\models;

use Yii;

class User extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'user';
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
        return Yii::$app->security->validatePassword($password, $this->password_hash);
    }

    //生成token
    public function generateAccessToken($now = null, $expires = null)
    {

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
        return static::find()->where(['id' => $id])->one();
    }
    public static function findIdentityByAccessToken($token, $type = null)
    {
        $claims = Yii::$app->jwt->parse($token)->claims();
        $uid = $claims->get('uid');
        $user = static::findIdentity($uid);
        return $user;
    }

}
