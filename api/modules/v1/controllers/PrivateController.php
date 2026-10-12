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
        return $searchModel->searchPrivate(Yii::$app->request->queryParams, Yii::$app->user->id, $papeSize);
    }

}
