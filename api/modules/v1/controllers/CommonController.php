<?php
namespace app\modules\v1\controllers;
use Yii;
use yii\rest\Controller;

//PlayerToken
use  app\modules\v1\models\PlayerToken;

class CommonController extends Controller
{

    public function behaviors()
    {
      
        $behaviors = parent::behaviors();
        return $behaviors;
    }
    public function actionTest(){
     
        $cache = Yii::$app->cache;
        $cache->set('test', '1234552s');
        $value = $cache->get('test');

        // 打印 Redis 缓存信息
        $redis = $cache->redis;
        $info = $redis->info();
        Yii::info($info, __METHOD__);
        return $info;
    }


    public function actionWatermark(){

        return [
            
            'success' => true,
            'message' => 'test message',
            'data' => [
                'show' => false,
            ]
        ];
    }
  }