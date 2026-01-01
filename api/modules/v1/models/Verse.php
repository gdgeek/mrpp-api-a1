<?php

namespace app\modules\v1\models;




use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\db\Expression;
use yii\db\ActiveQuery;
use OpenApi\Annotations as OA;

/**
 * This is the model class for table "verse".
 *
 * @OA\Schema(
 *     schema="Verse",
 *     title="场景",
 *     description="场景数据模型",
 *     @OA\Property(property="id", type="integer", description="ID"),
 *     @OA\Property(property="name", type="string", description="场景名称"),
 *     @OA\Property(property="description", type="string", description="描述"),
 *     @OA\Property(property="image", ref="#/components/schemas/File", description="封面图片"),
 *     @OA\Property(property="author_id", type="integer", description="作者ID"),
 *     @OA\Property(property="created_at", type="string", format="date-time", description="创建时间"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", description="更新时间"),
 *     @OA\Property(property="status", type="integer", description="状态"),
 *     @OA\Property(property="type", type="integer", description="类型")
 * )
 *
 * @property int $id
* @property int $author_id
* @property int|null $updater_id
* @property string $created_at
* @property string $updated_at
* @property string $name
* @property string $uuid
* @property string $description
* @property string|null $info
* @property int|null $image_id
* @property string|null $data
* @property int|null $version
*
* @property Manager[] $managers
* @property Meta[] $metas
* @property User $author
* @property File $image
* @property User $updater

*/
class Verse extends \yii\db\ActiveRecord
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
            ],
            [
                'class' => BlameableBehavior::class,
                'createdByAttribute' => 'author_id',
                'updatedByAttribute' => 'updater_id',
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'verse';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['name'], 'required'],
            [['author_id', 'updater_id', 'image_id', 'version'], 'integer'],
            [['created_at', 'updated_at'], 'safe'],
            [['info', 'data'], 'string'],
            [['name', 'description', 'uuid'], 'string', 'max' => 255],
            [['uuid'], 'unique'],
            [['author_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::className(), 'targetAttribute' => ['author_id' => 'id']],
            [['image_id'], 'exist', 'skipOnError' => true, 'targetClass' => File::className(), 'targetAttribute' => ['image_id' => 'id']],
            [['updater_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::className(), 'targetAttribute' => ['updater_id' => 'id']],
        ];
    }

    public function extraFields()
    {


        return [
            'id',
            'name',
            'uuid',
            'metas',
            'data',
            'image',
            'resources',
            'description',
            'managers',
            'code' => function () {
                $verseCode = $this->verseCode;
                $cl = Yii::$app->request->get('cl');
                $substring = "";
                if (!$cl || $cl != 'js') {
                    $cl = 'lua';
                    $substring = "local verse = {}\n local is_playing = false\n";
                }
                if ($verseCode && $verseCode->code) {
                    $script = $verseCode->code->$cl;
                }

                if (isset($script)) {
                    if (strpos($script, $substring) !== false) {
                        return $script;
                    } else {
                        return $substring . $script;
                    }
                } else {
                    return $substring;
                }
            },
        ];
    }
    public function getVerseCode()
    {
        return $this->hasOne(VerseCode::className(), ['verse_id' => 'id']);
    }
    public function fields()
    {
        return [];
    }
    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'author_id' => 'Author ID',
            'updater_id' => 'Updater ID',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
            'name' => 'Name',
            'info' => 'Info',
            'uuid' => 'Uuid',
            'image_id' => 'Image Id',
            'data' => 'Data',
            'version' => 'Version',
            'description' => 'Description',
        ];
    }


    /**
     * Gets query for [[EventLinks]].
     *
     * @return \yii\db\ActiveQuery|EventLinkQuery
     */
    /*
    public function getEventLinks()
    {
    return $this->hasMany(EventLink::className(), ['verse_id' => 'id']);
    }
    */
    public function getResources()
    {
        $metas = $this->metas;

        $ids = [];

        foreach ($metas as $meta) {
            $ids = array_merge_recursive($ids, $meta->resourceIds);
        }

        $items = Resource::find()->where(['id' => $ids])->all();
        return $items;
    }


    public function getNodes($inputs, $quest)
    {
        $m = [];
        $UUID = [];
        foreach ($inputs as $child) {
            $id = $child->parameters->id;
            $UUID[$id] = $child->parameters->uuid;
            array_push($m, $id);
        }

        $datas = $quest->where(['id' => $m])->all();

        foreach ($datas as $i => $item) {
            if (!$item->uuid) {
                $item->uuid = $UUID[$item->id];
                $item->save();
            }
        }

        return $datas;
    }


    /** 
     * Gets query for [[Managers]]. 
     * 
     * @return \yii\db\ActiveQuery 
     */
    public function getManagers()
    {
        return $this->hasMany(Manager::className(), ['verse_id' => 'id']);
    }


    /**
     * Gets query for [[Metas]].
     *
     * @return \yii\db\ActiveQuery|MetaQuery
     */
    public function getMetas()
    {
        $ret = [];
        if (is_string($this->data)) {
            $data = json_decode($this->data);
        } else {
            $data = json_decode(json_encode($this->data));
        }

        if (isset($data->children)) {
            foreach ($data->children->modules as $item) {
                $ret[] = $item->parameters->meta_id;
            }
        }

        return Meta::find()->where(['id' => $ret])->all();

    }

    /**
     * Gets query for [[VerseProperties]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getVerseProperties()
    {
        return $this->hasMany(VerseProperty::className(), ['verse_id' => 'id']);
    }
    public function getProperties()
    {
        return $this->hasMany(Property::className(), ['id' => 'property_id'])
            ->viaTable('verse_property', ['verse_id' => 'id']);
    }
    /**
     * Gets query for [[Author]].
     *
     * @return \yii\db\ActiveQuery|UserQuery
     */
    public function getAuthor(): ActiveQuery
    {
        return $this->hasOne(User::className(), ['id' => 'author_id']);
    }

    /**
     * Gets query for [[Image]].
     *
     * @return \yii\db\ActiveQuery|FileQuery
     */
    public function getImage()
    {
        return $this->hasOne(File::className(), ['id' => 'image_id']);
    }

    /**
     * Gets query for [[Updater]].
     *
     * @return \yii\db\ActiveQuery|UserQuery
     */
    public function getUpdater()
    {
        return $this->hasOne(User::className(), ['id' => 'updater_id']);
    }

    /**
     * {@inheritdoc}
     * @return VerseQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new VerseQuery(get_called_class());
    }
    /**
     * Gets query for [[VerseRetes]].
     *
     * @return \yii\db\ActiveQuery|VerseReteQuery
     */
    public function getVerseRetes()
    {
        return $this->hasMany(VerseRete::className(), ['verse_id' => 'id']);
    }
    public function getShare()
    {

        $share = VerseShare::findOne(['verse_id' => $this->id, 'user_id' => Yii::$app->user->id]);

        return $share != null;
    }

    /**
     * Gets query for [[VerseScripts]].
     *
     * @return \yii\db\ActiveQuery|VerseScriptQuery
     */
    public function getVerseScripts()
    {
        return $this->hasMany(VerseScript::className(), ['verse_id' => 'id']);
    }
    public function getScript()
    {
        return $this->hasOne(VerseScript::className(), ['verse_id' => 'id']);
    }

}

