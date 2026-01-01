<?php

namespace app\modules\v1\models;

use Yii;
use OpenApi\Annotations as OA;

/**
 * This is the model class for table "tags".
 *
 * @OA\Schema(
 *     schema="Tags",
 *     title="标签",
 *     description="标签模型",
 *     @OA\Property(property="id", type="integer", description="ID"),
 *     @OA\Property(property="name", type="string", description="名称"),
 *     @OA\Property(property="key", type="string", description="唯一标识"),
 *     @OA\Property(property="type", type="string", description="类型")
 * )
 *
 * @property int $id
 * @property string|null $name
 * @property string|null $key
 * @property string $type
 *
 * @property MessageTags[] $messageTags
 * @property VerseTags[] $verseTags
 */
class Tags extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'tags';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
           
            [['type'], 'string'],
            [['name', 'key'], 'string', 'max' => 255],
            [['key'], 'unique'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'name' => 'Name',
            'key' => 'Key',
            'type' => 'Type',
        ];
    }

    /**
     * Gets query for [[MessageTags]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getMessageTags()
    {
        return $this->hasMany(MessageTags::className(), ['tag_id' => 'id']);
    }

    /**
     * Gets query for [[VerseTags]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getVerseTags()
    {
        return $this->hasMany(VerseTags::className(), ['tags_id' => 'id']);
    }
}
