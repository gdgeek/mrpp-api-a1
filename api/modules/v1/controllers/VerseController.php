<?php
namespace app\modules\v1\controllers;
use app\modules\v1\models\VerseSearch;
use yii\web\BadRequestHttpException;

//use app\modules\v1\models\VerseReleaseSearch;
use yii\helpers\HtmlPurifier;
use yii\rest\ActiveController;
use Yii;
use OpenApi\Annotations as OA;

use app\components\policies\VersePolicy;

/**
 * @OA\Tag(
 *     name="Verse",
 *     description="场景元数据接口"
 * )
 */
class VerseController extends ActiveController
{
    private VersePolicy $versePolicy;
    private VerseSearch $verseSearch;

    public function __construct(
        $id,
        $module,
        VersePolicy $versePolicy,
        VerseSearch $verseSearch,
        $config = []
    ) {
        $this->versePolicy = $versePolicy;
        $this->verseSearch = $verseSearch;
        parent::__construct($id, $module, $config);
    }

    public $modelClass = 'app\modules\v1\models\Verse';
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

    /**
     * Checks the privilege of the current user.
     *
     * @param string $action the ID of the action to be executed
     * @param object $model the model to be accessed. If null, it means no specific model is being accessed.
     * @param array $params additional parameters
     * @throws \yii\web\ForbiddenHttpException if the user does not have access
     */
    public function checkAccess($action, $model = null, $params = [])
    {
        // Only check access for specific actions on a specific model
        if ($model && in_array($action, ['view', 'update', 'delete'])) {
            $user = Yii::$app->user->identity;
            $can = false;

            switch ($action) {
                case 'view':
                    $can = $this->versePolicy->canView($user, $model);
                    break;
                case 'update':
                    $can = $this->versePolicy->canUpdate($user, $model);
                    break;
                case 'delete':
                    $can = $this->versePolicy->canDelete($user, $model);
                    break;
            }

            if (!$can) {
                throw new \yii\web\ForbiddenHttpException("You are not allowed to $action this verse.");
            }
        }
    }


    /**
     * @OA\Get(
     *     path="/v1/verse/index",
     *     summary="获取我的场景列表",
     *     tags={"Verse"},
     *     security={{"Bearer": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="成功",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Verse"))
     *     )
     * )
     */
    public function actionIndex()
    {
        $searchModel = clone $this->verseSearch;
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
        $dataProvider->query->andWhere(['author_id' => Yii::$app->user->id]);


        $tags = Yii::$app->request->get('tags');

        // 如果tags参数存在，将其转换为数字数组
        if ($tags) {
            $tagsArray = array_map('intval', explode(',', $tags));
            if (isset($tagsArray) && !empty($tagsArray)) {
                // 假设有一个 verse_tags 表，包含 verse_id 和 tag_id 字段
                $dataProvider->query->innerJoin('verse_tags', 'verse_tags.verse_id = verse.id')
                    ->andWhere(['in', 'verse_tags.tags_id', $tagsArray])
                    ->groupBy('verse.id'); // 避免重复结果
            }
        }

        return $dataProvider;
    }
    /**
     * @OA\Get(
     *     path="/v1/verse/public",
     *     summary="获取公开场景列表",
     *     tags={"Verse"},
     *     @OA\Parameter(name="tags", in="query", description="标签过滤", @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="成功",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Verse"))
     *     )
     * )
     */
    public function actionPublic()
    {
        $searchModel = clone $this->verseSearch;
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        // 合并查询：直接在主查询中添加标签条件
        $dataProvider->query->innerJoin('verse_tags AS vt_public', 'vt_public.verse_id = verse.id')
            ->innerJoin('tags AS t_public', 't_public.id = vt_public.tags_id')
            ->andWhere(['t_public.key' => 'public']);

        // 处理额外的标签过滤
        $tags = Yii::$app->request->get('tags');
        // 如果tags参数存在，将其转换为数字数组
        if ($tags) {
            $tagsArray = array_map('intval', explode(',', $tags));
            if (isset($tagsArray) && !empty($tagsArray)) {
                // 假设有一个 verse_tags 表，包含 verse_id 和 tag_id 字段
                $dataProvider->query->innerJoin('verse_tags', 'verse_tags.verse_id = verse.id')
                    ->andWhere(['in', 'verse_tags.tags_id', $tagsArray])
                    ->groupBy('verse.id'); // 避免重复结果
            }
        }

        return $dataProvider;
    }

    public function actionOpen()
    {
        $searchModel = clone $this->verseSearch;
        $papeSize = Yii::$app->request->get('pageSize', 15);
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams, $papeSize);
        $query = $dataProvider->query;
        $query->select('verse.*')->leftJoin('verse_open', '`verse_open`.`verse_id` = `verse`.`id`')->andWhere(['NOT', ['verse_open.id' => null]]);
        return $dataProvider;
    }
    public function actionRelease()
    {
        if (!isset(Yii::$app->request->queryParams['code'])) {
            throw new BadRequestHttpException('缺乏 code 数据');
        }
        $code = Yii::$app->request->queryParams['code'];
        $searchModel = clone $this->verseSearch;
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
        $query = $dataProvider->query;

        $query->select('verse.*')->leftJoin('verse_release', '`verse_release`.`verse_id` = `verse`.`id`')->andWhere(['verse_release.code' => $code]);
        return $query->one();
    }
}
