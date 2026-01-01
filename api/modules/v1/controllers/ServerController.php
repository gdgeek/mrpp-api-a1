<?php
namespace app\modules\v1\controllers;
use app\modules\v1\models\SnapshotSearch;
use app\modules\v1\models\TagsSearch;
use yii\web\BadRequestHttpException;
use app\modules\v1\models\Snapshot;
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
    private SnapshotSearch $snapshotSearch;
    private TagsSearch $tagsSearch;

    public function __construct(
        $id,
        $module,
        SnapshotSearch $snapshotSearch,
        TagsSearch $tagsSearch,
        $config = []
    ) {
        $this->snapshotSearch = $snapshotSearch;
        $this->tagsSearch = $tagsSearch;
        parent::__construct($id, $module, $config);
    }

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
     *         description="返回打卡场景快照列表",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/Snapshot")
     *         )
     *     )
     * )
     */
    public function actionCheckin()
    {
        $searchModel = clone $this->snapshotSearch;
        return $searchModel->searchCheckin(Yii::$app->request->queryParams);
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
     *         description="返回公开场景快照列表",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/Snapshot")
     *         )
     *     )
     * )
     */
    public function actionPublic()
    {
        $searchModel = clone $this->snapshotSearch;
        $pageSize = Yii::$app->request->get('pageSize', 15);
        return $searchModel->searchPublic(Yii::$app->request->queryParams, $pageSize);
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
     *         description="返回用户组内的场景快照列表",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/Snapshot")
     *         )
     *     ),
     *     @OA\Response(response=401, description="未授权")
     * )
     */
    public function actionGroup()
    {
        $userId = Yii::$app->user->id;
        $pageSize = Yii::$app->request->get('pageSize', 15);
        
        $searchModel = clone $this->snapshotSearch;
        return $searchModel->searchGroup(Yii::$app->request->queryParams, $userId, $pageSize);
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
     *         description="返回用户私有场景快照列表",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/Snapshot")
     *         )
     *     ),
     *     @OA\Response(response=401, description="未授权")
     * )
     */
    public function actionPrivate()
    {
        $searchModel = clone $this->snapshotSearch;
        $pageSize = Yii::$app->request->get('pageSize', 15);
        return $searchModel->searchPrivate(Yii::$app->request->queryParams, Yii::$app->user->id, $pageSize);
    }

    /**
     * @OA\Get(
     *     path="/v1/server/tags",
     *     summary="获取所有分类标签",
     *     tags={"Server"},
     *     @OA\Response(
     *         response=200,
     *         description="返回所有类型为 Classify 的标签列表",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Tags"))
     *     )
     * )
     */
    public function actionTags()
    {
        $searchModel = clone $this->tagsSearch;
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
     *         description="返回快照详情",
     *         @OA\JsonContent(ref="#/components/schemas/Snapshot")
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
