<?php
namespace app\modules\v1\controllers;
use app\modules\v1\models\SnapshotSearch;
use app\modules\v1\models\TagsSearch;
use yii\web\BadRequestHttpException;
use app\modules\v1\models\Snapshot;
use app\modules\v1\models\User;
use yii\helpers\HtmlPurifier;
use bizley\jwt\JwtHttpBearerAuth;
use yii\filters\auth\CompositeAuth;
use yii\rest\Controller;
use Yii;
class ServerController extends Controller
{

    public function behaviors()
    {
        $behaviors = parent::behaviors();
       
        $behaviors['authenticator'] = [
            'class' => CompositeAuth::class,
            'authMethods' => [
                JwtHttpBearerAuth::class,
            ],
            'except' => ['options','public','test','tags','snapshot','checkin'],
        ];
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
    public function actionCheckin(): string
    {
       $searchModel = new SnapshotSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
        // $dataProvider->query->andWhere(['author_id' => Yii::$app->user->id]);



        $query = $dataProvider->query;
        //链接tag表进行过滤。tag表的key为checkin,通过verse_tags链接
        $query->innerJoin('verse_property AS vp', 'vp.verse_id  = snapshot.verse_id')
            ->innerJoin('property', 'property.id = vp.property_id')
            ->andWhere(['property.key' => 'checkin'])->groupBy(['snapshot.id']);

        return $dataProvider;
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
    public function actionGroup()
    {
        $userId = Yii::$app->user->id;
        
        // 使用子查询合并为一次查询
        // group_user -> group_verse -> snapshot
        $verseIdsSubQuery = (new \yii\db\Query())
            ->select('gv.verse_id')
            ->from('group_verse gv')
            ->innerJoin('group_user gu', 'gu.group_id = gv.group_id')
            ->where(['gu.user_id' => $userId]);

        $pageSize = (int)Yii::$app->request->get('pageSize', 15);
        $page = (int)Yii::$app->request->get('page', 1);
        
        $query = Snapshot::find()
            ->where(['verse_id' => $verseIdsSubQuery])
            ->cache(30);

        $dataProvider = new \yii\data\ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => $pageSize,
                'page' => $page - 1,
            ],
        ]);
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
