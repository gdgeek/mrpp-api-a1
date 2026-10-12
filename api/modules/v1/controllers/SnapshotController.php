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
        return $searchModel->search(Yii::$app->request->queryParams);
    }
    public function actionPublic()
    {
        $searchModel = new SnapshotSearch();
        return $searchModel->searchPublic(Yii::$app->request->queryParams);
    }

}
