<?php

namespace app\modules\v2\controllers;

use yii\rest\Controller;
use yii\filters\auth\CompositeAuth;
use bizley\jwt\JwtHttpBearerAuth;

use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="v2 System",
 *     description="v2 系统相关接口"
 * )
 */
class SystemController extends Controller
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['authenticator'] = [
            'class' => CompositeAuth::class,
            'authMethods' => [
                JwtHttpBearerAuth::class,
            ],
            'optional' => ['index'],
        ];
        return $behaviors;
    }

    /**
     * @OA\Head(
     *     path="/v2/system",
     *     summary="系统连通性检查 (HEAD)",
     *     description="用于检查网络通畅性",
     *     tags={"v2 System"},
     *     @OA\Response(response=200, description="网络通畅")
     * )
     * @OA\Get(
     *     path="/v2/system",
     *     summary="系统健康检查 (GET)",
     *     description="获取系统健康状态",
     *     tags={"v2 System"},
     *     @OA\Response(
     *         response=200,
     *         description="系统运行正常",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="message", type="string", example="Service is operating normally"),
     *             @OA\Property(property="timestamp", type="integer")
     *         )
     *     )
     * )
     */
    public function actionIndex()
    {
        return [
            'status' => 'ok',
            'message' => 'Service is operating normally',
            'timestamp' => time(),
        ];
    }
}
