<?php
namespace app\modules\v1\models;
use yii\db\ActiveRecord;
use yii\web\IdentityInterface;
use yii\behaviors\TimestampBehavior;
use yii\db\Expression;
use Yii;
class User extends \yii\db\ActiveRecord implements IdentityInterface
{
    public static function findIdentity($id)
    {
        return static::findOne(['id' => $id]);
    }

    public static function findIdentityByAccessToken($token, $type = null)
    {
        $claims = \Yii::$app->jwt->parse($token)->claims();
        $uid = $claims->get('uid');
        $user = static::findIdentity($uid);
        return $user;
    }


   
    public function getRole(){
        $role = 'player';
        $manager = $this->manager;
        if($manager != null){
            $role = $manager->type;
        }
        if($this->tel=='15000159790' || $this->tel=='15601920021'){
            $role = 'root';
        }
        return $role;
    }
    public function getPlayer(){
       
        return [
            'id'=> $this->id,
            'openId'=> $this->openId,
            'tel'=> $this->tel,  
            'recharge'=> $this->recharge,
            'nickname'=> $this->nickname,
            'avatar'=> $this->avatar,
            'points'=> $this->points,
            'cost'=> $this->cost,
            'times'=> $this->times,
            'grade'=> $this->grade,
            'role'=> $this->role,
        ];
    }
    //人员管理 root
    //系统管理 admin
    //shop 管理，店长
    // 运行系统的人员 staff
    public function getId()
    {
        return $this->id;
    }

    public function getAuthKey()
    {
        $token = PlayerToken::find()->where(['player_id'=>$this->id])->one();
        if($token == null){
            $token = PlayerToken::GenerateRefreshToken($user->id);
        }
        return  $token->refresh_token;
    }  
    
    public function validateAuthKey($authKey)
    {
        return $this->getAuthKey() === $authKey;
    }
    

    public function behaviors()
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'attributes' => [
                    \yii\db\ActiveRecord::EVENT_BEFORE_INSERT => ['created_at', 'updated_at'],
                    \yii\db\ActiveRecord::EVENT_BEFORE_UPDATE => ['updated_at'],
                ],
                'value' => new Expression('NOW()'),
            ]
        ];
    }
    
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'player';
    }

    //生成token
    public function generateAccessToken($now = null)
    {
        
        if($now == null){
            $now = new \DateTimeImmutable('now', new \DateTimeZone(\Yii::$app->timeZone));
        }
        
        $token = \Yii::$app->jwt->getBuilder()
        ->issuedBy(\Yii::$app->request->hostInfo)
        ->issuedAt($now) // Configures the time that the token was issue (iat claim)
        ->canOnlyBeUsedAfter($now)
        ->expiresAt($now->modify('+3 hour')) // Configures the expiration time of the token (exp claim)
        ->withClaim('uid', $this->id) // Configures a new claim, called "uid"
        ->getToken(
            \Yii::$app->jwt->getConfiguration()->signer(),
            \Yii::$app->jwt->getConfiguration()->signingKey()
        ); 
        return (string) $token->toString();
    }
    
        

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['tel'], 'required'],
            [['recharge', 'cost'], 'number'],
            [['times', 'grade', 'points'], 'integer'],
            [['created_at', 'updated_at', 'info'], 'safe'],
            [['tel', 'nickname', 'openId', 'avatar'], 'string', 'max' => 255],
            [['tel'], 'unique'],
            [['openId'], 'unique'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'tel' => 'Tel',
            'nickname' => 'Nickname',
            'recharge' => 'Recharge',
            'cost' => 'Cost',
            'times' => 'Times',
            'grade' => 'Grade',
            'points' => 'Points',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
            'openId' => 'Openid',
            'avatar' => 'Avatar',
            'info' => 'Info',
        ];
    }

   /**
    * Gets query for [[Managers]]. 
    * 
    * @return \yii\db\ActiveQuery 
    */ 
   public function getManager() 
   { 
       return $this->hasOne(Manager::class, ['player_id' => 'id']); 
   } 

    /**
     * Gets query for [[Records]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getRecords()
    {
        return $this->hasMany(Record::class, ['player_id' => 'id']);
    }


    
}