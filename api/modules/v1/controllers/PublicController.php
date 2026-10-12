<?php
namespace app\modules\v1\controllers;
use app\modules\v1\models\SnapshotSearch;
use yii\web\BadRequestHttpException;
use app\modules\v1\models\Snapshot;
use app\components\SceneQueryFilters;

use bizley\jwt\JwtHttpBearerAuth;
use Yii;
use yii\filters\auth\CompositeAuth;

use yii\rest\Controller;
class PublicController extends Controller
{


    public function behaviors()
    {
        $behaviors = parent::behaviors();
        // unset($behaviors['authenticator']);

        return $behaviors;
    }
    
    public function actions()
    {
        $actions = parent::actions();
        // 禁用不需要的操作
        unset($actions['index'], $actions['view'], $actions['create'], $actions['update'], $actions['delete'], $actions['options']);
        return [];//$actions;
    }

    public function actionByUuid($uuid)
    {
        return $this->findPublicSnapshot(['snapshot.uuid' => $uuid]);
    }
    public function actionById($id)
    {
        return $this->findPublicSnapshot(['snapshot.id' => $id]);
    }
    public function actionByVerseId($verse_id)
    {
        return $this->findPublicSnapshot(['snapshot.verse_id' => $verse_id]);
    }

    public function actionList()
    {
        $searchModel = new SnapshotSearch();

        $papeSize = Yii::$app->request->get('pageSize', 15);

        return $searchModel->searchPublic(Yii::$app->request->queryParams, $papeSize);
    }

    private function findPublicSnapshot(array $condition): Snapshot
    {
        $query = Snapshot::find()->where($condition);
        SceneQueryFilters::applyPublic($query, 'snapshot.verse_id', ['public' => true]);
        $model = $query->one();
        if ($model === null) {
            throw new BadRequestHttpException('Snapshot not found.');
        }
        return $model;
    }

}
