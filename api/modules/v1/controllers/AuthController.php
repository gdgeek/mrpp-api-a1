<?php
namespace app\modules\v1\controllers;



use yii\web\BadRequestHttpException;
use app\modules\v1\models\User;
use Yii;

class AuthController extends \yii\rest\Controller
{


  public function behaviors()
  {

    $behaviors = parent::behaviors();

    return $behaviors;
  }
  public function actionRefresh()
  {

    $refreshToken = Yii::$app->request->post("refreshToken");
    if (!$refreshToken) {
      throw new BadRequestHttpException("refreshToken is required");
    }
    $user = User::findByRefreshToken($refreshToken);
    if (!$user) {
      throw new BadRequestHttpException("no user");
    }
    //$user->generateAuthKey();
    if ($user->validate()) {
      $user->save();
    } else {
      throw new BadRequestHttpException("save error");
    }
    return ['success' => true, 'message' => "refresh", 'nickname'=>$user->nickname, 'token' => $user->token()];

  }
  public function actionKeyToToken(){
    $key = Yii::$app->request->post("key");
    if (!$key) {
      throw new BadRequestHttpException("key is required");
    }
    $user = User::findByUserLinked($key);
    if (!$user) {
      throw new BadRequestHttpException("no user");
    }
    return ['success' => true, 'message' => "keyToToken", 'nickname'=>$user->nickname, 'token' => $user->token()];
  }
  public function actionLogin()
  {

    $username = Yii::$app->request->post("username");
    if (!$username) {
      throw new BadRequestHttpException("username is required");
    }
    $password = Yii::$app->request->post("password");
    if (!$password) {
      throw new BadRequestHttpException("password is required");
    }

    $user = User::findByUsername($username);
    if (!$user) {
      throw new BadRequestHttpException("no user");
    }
    if (!$user->validatePassword($password)) {
      throw new BadRequestHttpException("wrong password");
    }


    return ['success' => true, 'message' => "login", 'nickname'=>$user->nickname, 'token' => $user->token()];
  }

}