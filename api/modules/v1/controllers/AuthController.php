<?php
namespace app\modules\v1\controllers;



use yii\web\BadRequestHttpException;
use app\modules\v1\models\User;
use Yii;
use OpenApi\Annotations as OA;

/**
 * 认证控制器
 *
 * @OA\Tag(
 *     name="Auth",
 *     description="用户认证相关接口"
 * )
 */
class AuthController extends \yii\rest\Controller
{


  public function behaviors()
  {

    $behaviors = parent::behaviors();

    return $behaviors;
  }

  /**
   * @OA\Post(
   *     path="/v1/auth/refresh",
   *     summary="刷新访问令牌",
   *     tags={"Auth"},
   *     @OA\RequestBody(
   *         required=true,
   *         @OA\JsonContent(
   *             required={"refreshToken"},
   *             @OA\Property(property="refreshToken", type="string", description="刷新令牌")
   *         )
   *     ),
   *     @OA\Response(
   *         response=200,
   *         description="刷新成功，返回新的访问令牌",
   *         @OA\JsonContent(
   *             @OA\Property(property="success", type="boolean", example=true),
   *             @OA\Property(property="message", type="string", example="refresh"),
   *             @OA\Property(property="nickname", type="string", description="用户昵称"),
   *             @OA\Property(property="token", type="string", description="新的JWT令牌")
   *         )
   *     ),
   *     @OA\Response(response=400, description="请求参数错误")
   * )
   */
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

  /**
   * @OA\Post(
   *     path="/v1/auth/key-to-token",
   *     summary="通过关联key获取令牌",
   *     tags={"Auth"},
   *     @OA\RequestBody(
   *         required=true,
   *         @OA\JsonContent(
   *             required={"key"},
   *             @OA\Property(property="key", type="string", description="用户关联key")
   *         )
   *     ),
   *     @OA\Response(
   *         response=200,
   *         description="获取成功",
   *         @OA\JsonContent(
   *             @OA\Property(property="success", type="boolean", example=true),
   *             @OA\Property(property="message", type="string", example="keyToToken"),
   *             @OA\Property(property="nickname", type="string", description="用户昵称"),
   *             @OA\Property(property="token", type="string", description="JWT令牌"),
   *             @OA\Property(property="user", ref="#/components/schemas/User")
   *         )
   *     ),
   *     @OA\Response(response=400, description="key无效或用户不存在")
   * )
   */
  public function actionKeyToToken(){
    $key = Yii::$app->request->post("key");
    if (!$key) {
      throw new BadRequestHttpException("key is required");
    }
    $user = User::findByUserLinked($key);
    if (!$user) {
      throw new BadRequestHttpException("no user");
    }
    return ['success' => true, 'message' => "keyToToken", 'nickname'=>$user->nickname, 'token' => $user->token(), 'user' => $user];
  }

  /**
   * @OA\Post(
   *     path="/v1/auth/login",
   *     summary="用户登录",
   *     tags={"Auth"},
   *     @OA\RequestBody(
   *         required=true,
   *         @OA\JsonContent(
   *             required={"username", "password"},
   *             @OA\Property(property="username", type="string", description="用户名"),
   *             @OA\Property(property="password", type="string", description="密码")
   *         )
   *     ),
   *     @OA\Response(
   *         response=200,
   *         description="登录成功",
   *         @OA\JsonContent(
   *             @OA\Property(property="success", type="boolean", example=true),
   *             @OA\Property(property="message", type="string", example="login"),
   *             @OA\Property(property="nickname", type="string", description="用户昵称"),
   *             @OA\Property(property="token", type="string", description="JWT令牌"),
   *             @OA\Property(property="user", ref="#/components/schemas/User")
   *         )
   *     ),
   *     @OA\Response(response=400, description="用户名或密码错误")
   * )
   */
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



    return ['success' => true, 'message' => "login", 'nickname'=>$user->nickname, 'token' => $user->token(), 'user' => $user];
  }

}