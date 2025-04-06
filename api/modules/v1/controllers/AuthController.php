<?php
namespace app\modules\v1\controllers;
use Yii;
use yii\rest\Controller;
use app\modules\v1\models\Player;
use bizley\jwt\JwtHttpBearerAuth;
use app\modules\v1\models\User;
use yii\filters\auth\CompositeAuth;
use app\modules\v1\helper\PlayerFingerprintAuth;

//PlayerToken
use  app\modules\v1\models\PlayerToken;

class AuthController extends Controller
{

    public function behaviors()
    {
      
        $behaviors = parent::behaviors();
        /*
         //如果 action 不是 test
        if(Yii::$app->controller->action->id != 'test'
        && Yii::$app->controller->action->id != 'refresh-token'
        ){
          $behaviors['authenticator'] = [
              'class' => PlayerFingerprintAuth::className(),
          ];
        }*/
        return $behaviors;
    }
    
    public function actionLogin(){
        
    }
  }