<?php
namespace app\modules\v1\controllers;
use app\modules\v1\models\SnapshotSearch;
use yii\web\BadRequestHttpException;
use app\modules\v1\models\Snapshot;

use bizley\jwt\JwtHttpBearerAuth;
use Yii;
use yii\filters\auth\CompositeAuth;

use yii\rest\Controller;
class PrivateController extends Controller
{

    
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        // unset($behaviors['authenticator']);
        $behaviors['authenticator'] = [
            'class' => CompositeAuth::class,
            'authMethods' => [
                JwtHttpBearerAuth::class,
            ],
            'except' => ['options'],
        ];
        return $behaviors;
    }


    public function actionByUuid($uuid)
    {


        $model = Snapshot::find()
            ->where(['snapshot.uuid' => $uuid])
            ->innerJoin('verse AS v1', 'v1.id = snapshot.verse_id')
            ->andWhere(['v1.author_id' => Yii::$app->user->id])
            ->one();

        if ($model === null) {
            throw new BadRequestHttpException('Snapshot not found.');
        }

        return $model;
    }
    public function actionById($id)
    {
        $model = Snapshot::find()
            ->where(['snapshot.id' => $id])
            ->innerJoin('verse AS v1', 'v1.id = snapshot.verse_id')
            ->andWhere(['v1.author_id' => Yii::$app->user->id])
            ->one();

        if ($model === null) {
            throw new BadRequestHttpException('Snapshot not found.');
        }

        return $model;
    }
    public function actionByVerseId($verse_id)
    {

        $model = Snapshot::find()
            ->where(['snapshot.verse_id' => $verse_id])
            ->innerJoin('verse AS v1', 'v1.id = snapshot.verse_id')
            ->andWhere(['v1.author_id' => Yii::$app->user->id])
            ->one();

        if ($model === null) {
            throw new BadRequestHttpException('Snapshot not found.');
        }

        return $model;
    }

    public function actionList()
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

        return $dataProvider;
    }

}
