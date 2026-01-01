<?php

namespace app\modules\v2\controllers;

use app\modules\v1\models\Snapshot;
use app\modules\v1\models\SnapshotSearch;
use bizley\jwt\JwtHttpBearerAuth;
use yii\filters\auth\CompositeAuth;
use yii\rest\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="v2 Swapshot",
 *     description="v2 场景快照接口"
 * )
 */
class SnapshotController extends Controller
{
    private SnapshotSearch $snapshotSearch;

    public function __construct($id, $module, SnapshotSearch $snapshotSearch, $config = [])
    {
        $this->snapshotSearch = $snapshotSearch;
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
            'optional' => ['index', 'view'], 
        ];
        return $behaviors;
    }

    /**
     * @OA\Get(
     *     path="/v2/snapshots",
     *     summary="获取场景快照列表",
     *     description="根据 scope 参数获取不同范围的场景快照列表",
     *     tags={"v2 Swapshot"},
     *     @OA\Parameter(
     *         name="scope",
     *         in="query",
     *         description="查询范围: public(公开), checkin(打卡), group(组内), private(私有)",
     *         required=false,
     *         @OA\Schema(type="string", enum={"public", "checkin", "group", "private"}, default="public")
     *     ),
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
     *         description="返回场景快照列表",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/Snapshot")
     *         )
     *     ),
     *     @OA\Response(response=401, description="需要登录 (group/private scope)")
     * )
     */
    public function actionIndex()
    {
        $scope = Yii::$app->request->get('scope', 'public');
        $params = Yii::$app->request->queryParams;
        $pageSize = Yii::$app->request->get('pageSize', 15);
        
        $searchModel = clone $this->snapshotSearch;

        switch ($scope) {
            case 'checkin':
                return $searchModel->searchCheckin($params);
            case 'group':
                if (Yii::$app->user->isGuest) {
                    throw new ForbiddenHttpException('Login required.');
                }
                return $searchModel->searchGroup($params, Yii::$app->user->id, $pageSize);
            case 'private':
                if (Yii::$app->user->isGuest) {
                    throw new ForbiddenHttpException('Login required.');
                }
                return $searchModel->searchPrivate($params, Yii::$app->user->id, $pageSize);
            case 'public':
            default:
                // Default to public scope logic
                return $searchModel->searchPublic($params, $pageSize);
        }
    }

    /**
     * @OA\Get(
     *     path="/v2/snapshots/{id}",
     *     summary="获取单个场景快照详情",
     *     tags={"v2 Swapshot"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="快照 ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="返回快照详情",
     *         @OA\JsonContent(ref="#/components/schemas/Snapshot")
     *     ),
     *     @OA\Response(response=404, description="未找到")
     * )
     */
    public function actionView($id)
    {
        $model = Snapshot::find()->where(['id' => $id])->cache(30)->one();
        if (!$model) {
            throw new NotFoundHttpException("Object not found: $id");
        }
        return $model;
    }
}
