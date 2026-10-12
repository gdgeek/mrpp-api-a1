<?php

namespace app\modules\v1\models;

use Yii;
use yii\behaviors\TimestampBehavior;
use yii\db\Expression;
/**
 * This is the model class for table "refresh_token".
 *
 * @property int $id
 * @property int $user_id
 * @property string $key
 * @property string|null $created_at
 *
 * @property User $user
 */
class UserLinked extends \yii\db\ActiveRecord
{

    public function save($runValidation = true, $attributeNames = null)
    {
        \app\components\IdentityAuthority::forbidLocalCredentials();
        return parent::save($runValidation, $attributeNames);
    }

    public function beforeSave($insert)
    {
        \app\components\IdentityAuthority::forbidLocalCredentials();
        return parent::beforeSave($insert);
    }

    public function beforeDelete()
    {
        \app\components\IdentityAuthority::forbidLocalCredentials();
        return parent::beforeDelete();
    }

    public static function updateAll($attributes, $condition = '', $params = [])
    {
        \app\components\IdentityAuthority::forbidLocalCredentials();
        return parent::updateAll($attributes, $condition, $params);
    }

    public static function updateAllCounters($counters, $condition = '', $params = [])
    {
        \app\components\IdentityAuthority::forbidLocalCredentials();
        return parent::updateAllCounters($counters, $condition, $params);
    }

    public static function deleteAll($condition = null, $params = [])
    {
        \app\components\IdentityAuthority::forbidLocalCredentials();
        return parent::deleteAll($condition, $params);
    }

        public function behaviors()
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'attributes' => [
               //     \yii\db\ActiveRecord::EVENT_BEFORE_INSERT => ['created_at'],
                    \yii\db\ActiveRecord::EVENT_BEFORE_UPDATE => ['created_at'],
                ],
                'value' => new Expression('NOW()'),
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'user_linked';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['user_id', 'key'], 'required'],
            [['user_id'], 'integer'],
            [['created_at'], 'safe'],
            [['key'], 'string', 'max' => 255],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::className(), 'targetAttribute' => ['user_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app', 'ID'),
            'user_id' => Yii::t('app', 'User ID'),
            'key' => Yii::t('app', 'Key'),
            'created_at' => Yii::t('app', 'Created At'),
        ];
    }

    /**
     * Gets query for [[User]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::className(), ['id' => 'user_id']);
    }
}
