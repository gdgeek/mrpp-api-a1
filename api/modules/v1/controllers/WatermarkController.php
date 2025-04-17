<?php
namespace app\modules\v1\controllers;
use Yii;
use yii\rest\Controller;
use app\modules\v1\models\Watermark;

class WatermarkController extends Controller
{

    public function behaviors()
    {
      
        $behaviors = parent::behaviors();
        return $behaviors;
    }
    public function actionVerify(){
        
        

        return [
            
            'success' => true,
            'message' => 'test message',
            'data' => [
                'show' => false,
            ]
        ];
    }
  }