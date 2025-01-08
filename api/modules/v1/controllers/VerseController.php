<?php
namespace app\modules\v1\controllers;
//use api\modules\v1\models\VerseSearch;
use yii\web\BadRequestHttpException;
//use api\modules\v1\models\VerseReleaseSearch;
use yii\helpers\HtmlPurifier;
use yii\rest\ActiveController;
use Yii;
class VerseController extends ActiveController
{
    
    public $modelClass = 'app\modules\v1\models\Verse';
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        
        return $behaviors;
    }
    public function actions()
    {
        $actions = parent::actions();
       // unset($actions['index']);
        unset($actions['create']);
        unset($actions['update']);
        unset($actions['delete']);
        unset($actions['options']);
        //  unset($actions['view']);
        return $actions;
    }
   
}
