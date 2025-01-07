<?php

namespace app\modules\v1\models;

use yii\behaviors\TimestampBehavior;
use yii\db\Expression;
use Yii;

/**
 * This is the model class for table "record".
 *
 * @property int $id
 * @property string $created_at
 * @property string|null $updated_at
 * @property string|null $award
 * @property int $player_id
 * @property int $device_id
 * @property int|null $points
 * @property string|null $startTime
 * @property string|null $endTime
 *
 * @property Device $device
 * @property Player $player
 */
class Record extends \yii\db\ActiveRecord
{

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
        return 'record';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['player_id', 'device_id'], 'required'],
            [['created_at', 'updated_at', 'award', 'startTime', 'endTime'], 'safe'],
            [['player_id', 'device_id', 'points'], 'integer'],
            [['player_id'], 'unique'],
            [['device_id'], 'unique'],
            [['device_id'], 'exist', 'skipOnError' => true, 'targetClass' => Device::class, 'targetAttribute' => ['device_id' => 'id']],
            [['player_id'], 'exist', 'skipOnError' => true, 'targetClass' => Player::class, 'targetAttribute' => ['player_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
            'award' => 'Award',
            'player_id' => 'Player ID',
            'device_id' => 'Device ID',
            'points' => 'Points',
            'startTime' => 'Start Time',
            'endTime' => 'End Time',
        ];
    }

    /**
     * Gets query for [[Device]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getDevice()
    {
        return $this->hasOne(Device::class, ['id' => 'device_id']);
    }

    /**
     * Gets query for [[Player]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getPlayer()
    {
        return $this->hasOne(Player::class, ['id' => 'player_id']);
    }
}
