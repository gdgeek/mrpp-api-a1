<?php
namespace app\modules\v1\controllers;

use yii\rest\ActiveController;
use yii\web\BadRequestHttpException;
use app\modules\v1\models\Phototype;

class PhototypeController extends ActiveController
{
    public $modelClass = 'app\modules\v1\models\Phototype';
    
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        // unset($behaviors['authenticator']);
      
        return $behaviors;
    }

    public function actionInfo(string $type)
    {
        $model = Phototype::find()->where(['type' => $type])->one();
        if($model){
            return $model->toArray(['id','data'],['resource']);
        }
        throw new BadRequestHttpException('model not found.'); 
     
    }
}