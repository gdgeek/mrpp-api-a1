<?php
namespace app\modules\v1\controllers;


use app\modules\v1\models\SnapshotSearch;
use yii\web\BadRequestHttpException;
use app\modules\v1\models\Snapshot;
use yii\db\Query;
use app\modules\v1\models\User;
use Yii;

class CheckinController extends \yii\rest\Controller
{


    public function behaviors()
    {

        $behaviors = parent::behaviors();

        return $behaviors;
    }
    public function actionList()
    {
        
        $searchModel = new SnapshotSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
        // $dataProvider->query->andWhere(['author_id' => Yii::$app->user->id]);


        $tags = Yii::$app->request->get('tags');

        // 如果tags参数存在，将其转换为数字数组
        if ($tags) {
            $tagsArray = array_map('intval', explode(',', $tags));
            if (isset($tagsArray) && !empty($tagsArray)) {
                // 使用 ActiveQuery 实例，无需强制转换
                $query = $dataProvider->query;

                $query->innerJoin('verse_tags', 'verse_tags.verse_id  = snapshot.verse_id')
                    ->andWhere(['in', 'verse_tags.tags_id', $tagsArray])
                    ->groupBy('verse.id'); // 避免重复结果
            }
        }

        return $dataProvider;
    }

}