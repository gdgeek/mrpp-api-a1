<?php

namespace app\modules\v2\controllers;

use app\modules\v1\models\TagsSearch;
use yii\rest\Controller;
use Yii;

use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="v2 Tags",
 *     description="v2 标签接口"
 * )
 */
class TagsController extends Controller
{
    private TagsSearch $tagsSearch;

    public function __construct($id, $module, TagsSearch $tagsSearch, $config = [])
    {
        $this->tagsSearch = $tagsSearch;
        parent::__construct($id, $module, $config);
    }

    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['authenticator'] = [
            'class' => \yii\filters\auth\CompositeAuth::class,
            'authMethods' => [
                \bizley\jwt\JwtHttpBearerAuth::class,
            ],
            'optional' => ['index'],
        ];
        $behaviors['verbs'] = [
            'class' => \yii\filters\VerbFilter::class,
            'actions' => [
                'index' => ['GET', 'HEAD'],
            ],
        ];
        return $behaviors;
    }

    /**
     * @OA\Get(
     *     path="/v2/tags",
     *     summary="获取标签列表 (read-only)",
     *     description="获取所有类型为 Classify 的标签",
     *     tags={"v2 Tags"},
     *     @OA\Response(
     *         response=200,
     *         description="返回标签列表",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/Tags")
     *         )
     *     )
     * )
     */
    public function actionIndex()
    {
        $searchModel = clone $this->tagsSearch;
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
        
        $dataProvider->query->andWhere(['type' => 'Classify']);
        $dataProvider->query->cache(30);
        
        return $dataProvider;
    }
}
