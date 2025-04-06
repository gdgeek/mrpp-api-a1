<?php
namespace app\modules\v1\controllers;
use app\modules\v1\models\SnapshotSearch;
use yii\web\BadRequestHttpException;
use app\modules\v1\models\Snapshot;


use yii\helpers\HtmlPurifier;
use yii\rest\ActiveController;
use Yii;
class SnapshotController extends ActiveController
{

    public $modelClass = 'app\modules\v1\models\Snapshot';
    public function behaviors()
    {
        $behaviors = parent::behaviors();

        return $behaviors;
    }
    public function actions()
    {
        $actions = parent::actions();
        unset($actions['index']);
        unset($actions['create']);
        unset($actions['update']);
        unset($actions['delete']);
        unset($actions['options']);
        //  unset($actions['view']);
        return $actions;
    }

    public function actionByUuid($uuid)
    {
        $model = Snapshot::find()
            ->where(['uuid' => $uuid])
            ->one();
        if ($model === null) {
            throw new BadRequestHttpException('Snapshot not found.');
        }
        return $model;
    }
    public function actionByVerseId($verse_id)
    {
        $model = Snapshot::find()
            ->where(['verse_id' => $verse_id])
            ->one();
        if ($model === null) {
            throw new BadRequestHttpException('Snapshot not found.');
        }
        return $model;
    }


    public function actionIndex()
    {

        $searchModel = new SnapshotSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
        // $dataProvider->query->andWhere(['author_id' => Yii::$app->user->id]);


        $tags = Yii::$app->request->get('tags');

        // 如果tags参数存在，将其转换为数字数组
        if ($tags) {
            $tagsArray = array_map('intval', explode(',', $tags));
            if (isset($tagsArray) && !empty($tagsArray)) {
                // 假设有一个 verse_tags 表，包含 verse_id 和 tag_id 字段
                $dataProvider->query->innerJoin('verse_tags', 'verse_tags.verse_id  = snapshot.verse_id')
                    // ->innerJoin('verse_tags', 'verse_tags.verse_id = verse.id')
                    ->andWhere(['in', 'verse_tags.tags_id', $tagsArray])
                    ->groupBy('verse.id'); // 避免重复结果
            }
        }

        return $dataProvider;
    }
    public function actionPublic()
    {
        $searchModel = new SnapshotSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        // 合并查询：直接在主查询中添加标签条件
        $dataProvider->query->innerJoin('verse_tags AS vt1', 'vt1.verse_id = snapshot.verse_id')
            ->innerJoin('tags', 'tags.id = vt1.tags_id')
            ->andWhere(['tags.key' => 'public']);

        // 处理额外的标签过滤
        $tags = Yii::$app->request->get('tags');
        // 如果tags参数存在，将其转换为数字数组
        if ($tags) {
            $tagsArray = array_map('intval', explode(',', $tags));
            if (isset($tagsArray) && !empty($tagsArray)) {
                // 假设有一个 verse_tags 表，包含 verse_id 和 tag_id 字段
                $dataProvider->query->innerJoin('verse_tags AS vt2', 'vt2.verse_id = snapshot.verse_id')
                    ->andWhere(['in', 'vt2.tags_id', $tagsArray])
                    ->groupBy('snapshot.id'); // 这里推测分组字段为snapshot.id，根据实际情况调整
            }
        }

        return $dataProvider;
    }

}
