<?php
namespace app\modules\v1\controllers;

use bizley\jwt\JwtHttpBearerAuth;
use yii\filters\auth\CompositeAuth;
use yii\rest\ActiveController;
use Yii;

use app\modules\v1\models\TagsSearch;
class TagsController extends ActiveController
{
    public $modelClass = 'app\modules\v1\models\Tags';
    public function behaviors()
    {
        $behaviors = parent::behaviors();

        // add CORS filter
        $behaviors['corsFilter'] = [
            'class' => \yii\filters\Cors::class,
            'cors' => [
                'Origin' => ['*'],
                'Access-Control-Request-Method' => ['GET'],
                'Access-Control-Request-Headers' => ['*'],
                'Access-Control-Allow-Credentials' => null,
                'Access-Control-Max-Age' => 86400,
                'Access-Control-Expose-Headers' => [
                    'X-Pagination-Total-Count',
                    'X-Pagination-Page-Count',
                    'X-Pagination-Current-Page',
                    'X-Pagination-Per-Page',
                ],
            ],
        ];
        return $behaviors;
    }
    public function actions()
    {
        return [];
    }
   
    public function actionIndex(){
        $searchModel = new TagsSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
        //返回所有type为Classify 的
        $dataProvider->query->andWhere(['type' => 'Classify']);
        return $dataProvider;
    }

    public function actionLogin(){
        
    }
 

}
