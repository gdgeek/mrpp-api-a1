<?php
namespace app\modules\v1\controllers;
use app\modules\v1\models\SnapshotSearch;
use app\modules\v1\models\TagsSearch;
use yii\web\BadRequestHttpException;
use app\modules\v1\models\Snapshot;
use app\modules\v1\models\User;
use yii\helpers\HtmlPurifier;
use yii\rest\Controller;
use Yii;
class ServerController extends Controller
{

    public function behaviors()
    {
        $behaviors = parent::behaviors();

        return $behaviors;
    }
    public function actions()
    {
        return [];
    }
    public function actionTest(): string
    {
        return "test";
    }
    public function actionPublic()
    {
        $searchModel = new SnapshotSearch();

        $papeSize = Yii::$app->request->get('pageSize', 15);

        $dataProvider = $searchModel->search(Yii::$app->request->queryParams, $papeSize);



        $dataProvider->query->innerJoin('verse_property AS vp1', 'vp1.verse_id = snapshot.verse_id')
            ->innerJoin('property', 'property.id = vp1.property_id')
            ->andWhere(['property.key' => 'public']);
        // 处理额外的标签过滤
        $tags = Yii::$app->request->get('tags');
        // 如果tags参数存在，将其转换为数字数组
        if ($tags) {
            $tagsArray = array_map('intval', explode(',', string: $tags));
            if (isset($tagsArray) && !empty($tagsArray)) {
                // 假设有一个 verse_tags 表，包含 verse_id 和 tag_id 字段
                $dataProvider->query->innerJoin('verse_tags AS vt2', 'vt2.verse_id = snapshot.verse_id')
                    ->andWhere(['in', 'vt2.tags_id', $tagsArray])
                    ->groupBy('snapshot.id'); // 这里推测分组字段为snapshot.id，根据实际情况调整
            }
        }

        // 读取结果缓存 30 秒（不改变分页/序列化行为）
        $dataProvider->query->cache(30);

        return $dataProvider;
    }
    public function actionPrivate()
    {
        $searchModel = new SnapshotSearch();

        $papeSize = Yii::$app->request->get('pageSize', defaultValue: 15);
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams, $papeSize);

        $dataProvider->query->innerJoin('verse AS v1', 'v1.id = snapshot.verse_id')
            ->andWhere(['in', 'v1.author_id', Yii::$app->user->id]);
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

        // 读取结果缓存 30 秒（SQL + 参数不同会生成不同缓存键）
        $dataProvider->query->cache(30);

        return $dataProvider;
    }

    public function actionTags()
    {
        $searchModel = new TagsSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
        //返回所有type为Classify 的
        $dataProvider->query->andWhere(['type' => 'Classify']);

        // 读取结果缓存 30 秒
        $dataProvider->query->cache(30);
        return $dataProvider;
    }

    public function actionSnapshot()
    {

        $id = Yii::$app->request->get('id');
        $verseId = Yii::$app->request->get('verse_id');

        if ($id === null && $verseId === null) {
            throw new BadRequestHttpException('id or verse_id is required.');
        }

        $query = Snapshot::find();
        $query->andFilterWhere(['snapshot.id' => $id !== null ? (int) $id : null]);
        $query->andFilterWhere(['snapshot.verse_id' => $verseId !== null ? (int) $verseId : null]);

        // 读取结果缓存 30 秒
        $query->cache(30);
        $model = $query->one();
        if ($model === null) {
            throw new BadRequestHttpException('Snapshot not found.');
        }
        return $model;
    }

  
}
