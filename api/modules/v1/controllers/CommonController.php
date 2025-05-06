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
    private function getUserData()
    {


        $refreshToken = Yii::$app->request->post("refreshToken");
        if (!$refreshToken) {
            throw new BadRequestHttpException('Refresh token is required.');
        }
        $user = User::findByRefreshToken($refreshToken);
        if (!$user) {
            throw new BadRequestHttpException('User not found.');
        }
        return [
            'nickname' => $user->nickname,
            'token' => $user->token(),
        ];




    }
    public function actionReport()
    {
        try {
            $user = $this->getUserData();
            return [
                'success' => true,
                'message' => "report success",
                'user' => $this->getUserData(),
                'data' => [
                    'watermark' => false,
                ]
            ];
        } catch (\Exception $e) {

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'user' => null,
                'data' => [
                    'watermark' => false,
                ]
            ];
        }
    }

    

    public function actionVerify()
    {
        $post = Yii::$app->request->post();
        //return $post;
        return [
            'success' => true,
            'message' => 'test message',
            'data' => [
                'watermark' => false,
                'shutdown' => false
            ]
        ];
    }
}