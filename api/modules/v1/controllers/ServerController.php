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
use OpenApi\Annotations as OA;

/**
 * 服务端 API 控制器
 *
 * @OA\Tag(
 *     name="Server",
 *     description="场景数据查询相关接口"
 * )
 */
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

    /**
     * @OA\Get(
     *     path="/v1/server/test",
     *     summary="测试接口",
     *     tags={"Server"},
     *     @OA\Response(response=200, description="返回 test 字符串")
     * )
     */
    public function actionTest(): string
    {
        return "test";
    }

    /**
     * @OA\Get(
     *     path="/v1/server/checkin",
     *     summary="获取打卡场景列表",
     *     tags={"Server"},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="页码",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="pageSize",
     *         in="query",
     *         description="每页数量",
     *         required=false,
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="返回打卡场景快照列表"
     *     )
     * )
     */
    public function actionCheckin()
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

    /**
     * @OA\Get(
     *     path="/v1/server/public",
     *     summary="获取公开发布的场景列表",
     *     tags={"Server"},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="页码",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="pageSize",
     *         in="query",
     *         description="每页数量",
     *         required=false,
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Parameter(
     *         name="tags",
     *         in="query",
     *         description="标签ID列表，逗号分隔",
     *         required=false,
     *         @OA\Schema(type="string", example="1,2,3")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="返回公开场景快照列表"
     *     )
     * )
     */
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

    /**
     * @OA\Get(
     *     path="/v1/server/group",
     *     summary="获取用户组内的场景列表",
     *     tags={"Server"},
     *     security={{"Bearer": {}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="页码",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="pageSize",
     *         in="query",
     *         description="每页数量",
     *         required=false,
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="返回用户组内的场景快照列表"
     *     ),
     *     @OA\Response(response=401, description="未授权")
     * )
     */
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

    /**
     * @OA\Get(
     *     path="/v1/server/private",
     *     summary="获取当前用户的私有场景列表",
     *     tags={"Server"},
     *     security={{"Bearer": {}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="页码",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="pageSize",
     *         in="query",
     *         description="每页数量",
     *         required=false,
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Parameter(
     *         name="tags",
     *         in="query",
     *         description="标签ID列表，逗号分隔",
     *         required=false,
     *         @OA\Schema(type="string", example="1,2,3")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="返回用户私有场景快照列表"
     *     ),
     *     @OA\Response(response=401, description="未授权")
     * )
     */
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

    /**
     * @OA\Get(
     *     path="/v1/server/tags",
     *     summary="获取所有分类标签",
     *     tags={"Server"},
     *     @OA\Response(
     *         response=200,
     *         description="返回所有类型为 Classify 的标签列表"
     *     )
     * )
     */
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

    /**
     * @OA\Get(
     *     path="/v1/server/snapshot",
     *     summary="根据ID或verse_id获取单个快照",
     *     tags={"Server"},
     *     @OA\Parameter(
     *         name="id",
     *         in="query",
     *         description="快照ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="verse_id",
     *         in="query",
     *         description="场景ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="返回快照详情"
     *     ),
     *     @OA\Response(response=400, description="参数错误或快照不存在")
     * )
     */
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
