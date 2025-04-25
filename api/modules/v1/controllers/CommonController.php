<?php
namespace app\modules\v1\controllers;
use Yii;
use yii\rest\Controller;

use yii\web\BadRequestHttpException;
use app\modules\v1\models\User;
//PlayerToken
use app\modules\v1\models\PlayerToken;

class CommonController extends Controller
{

    public function behaviors()
    {

        $behaviors = parent::behaviors();
        return $behaviors;
    }
    public function actionTest()
    {

        $cache = Yii::$app->cache;
        $cache->set('test', '1234552s');
        $value = $cache->get('test');

        // 打印 Redis 缓存信息
        $redis = $cache->redis;
        $info = $redis->info();
        Yii::info($info, __METHOD__);
        return $info;
    }
    private function getUserData(){
        
        try{
            $refreshToken = Yii::$app->request->post("refreshToken");
            if (!$refreshToken) {
                return null;
            }
            $user = User::findByRefreshToken($refreshToken);
            if (!$user) {
                return null;
            }
            return  [
                'nickname' => $user->nickname,
                'token' => $user->token(),
            ];
        }catch (\Exception $e){
           // Yii::error($e->getMessage(), __METHOD__);
            return null;
        }

       
      
    }
    public function actionReport()
    {
        return [
            'success' => true,
            'message' => "report success",
            'user' => $this->getUserData(),
            'data' => [
                'watermark' => false,
            ]
        ];


    }

    public function actionWatermark()
    {

        return [

            'success' => true,
            'message' => 'test message',
            'data' => [
                'show' => false,
            ]
        ];
    }
}