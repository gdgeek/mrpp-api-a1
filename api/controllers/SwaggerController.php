<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\Response;
use OpenApi\Generator;
use OpenApi\Annotations as OA;

/**
 * Swagger API 文档控制器
 *
 * @OA\Info(
 *     version="1.0.0",
 *     title="MrPP API v1",
 *     description="MrPP 元宇宙场景管理 RESTful API 文档"
 * )
 * @OA\Server(url="/", description="API Server")
 * @OA\SecurityScheme(
 *     securityScheme="Bearer",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 */
class SwaggerController extends Controller
{
    public $enableCsrfValidation = false;

    /**
     * 在执行任何操作前进行身份验证
     * 使用 HTTP Basic Authentication 保护 Swagger 文档
     * 凭据配置在 config/params.php 的 'swagger' 键中
     */
    public function beforeAction($action)
    {
        // 从配置文件读取凭据
        $swaggerConfig = Yii::$app->params['swagger'] ?? null;

        if (!$swaggerConfig) {
            throw new \yii\web\ServerErrorHttpException('Swagger 配置未找到');
        }

        // 检查 HTTP Basic Auth 凭据
        $username = $_SERVER['PHP_AUTH_USER'] ?? null;
        $password = $_SERVER['PHP_AUTH_PW'] ?? null;

        if ($username !== $swaggerConfig['username'] || $password !== $swaggerConfig['password']) {
            header('WWW-Authenticate: Basic realm="Swagger API Documentation"');
            header('HTTP/1.0 401 Unauthorized');
            echo '需要认证才能访问 API 文档';
            exit;
        }

        return parent::beforeAction($action);
    }

    /**
     * 渲染 Swagger UI 界面
     */
    public function actionIndex()
    {
        $swaggerUiUrl = Yii::$app->request->baseUrl . '/swagger-ui';

        // 动态获取协议，使用 urlManager 创建相对路径 URL 以避免 mixed-content 问题
        $jsonSchemaUrl = Yii::$app->urlManager->createUrl(['swagger/json-schema']);

        return <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API 文档 - Swagger UI</title>
    <link rel="stylesheet" href="{$swaggerUiUrl}/swagger-ui.css">
    <style>
        body { margin: 0; padding: 0; }
        .swagger-ui .topbar { display: none; }
    </style>
</head>
<body>
    <div id="swagger-ui"></div>
    <script src="{$swaggerUiUrl}/swagger-ui-bundle.js"></script>
    <script>
        window.onload = function() {
            SwaggerUIBundle({
                url: "{$jsonSchemaUrl}",
                dom_id: '#swagger-ui',
                deepLinking: true,
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIBundle.SwaggerUIStandalonePreset
                ],
                layout: "BaseLayout"
            });
        };
    </script>
</body>
</html>
HTML;
    }

    /**
     * 生成 OpenAPI JSON Schema
     */
    public function actionJsonSchema()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $baseDir = dirname(__DIR__); // api/

        // 配置需要扫描的文件列表
        $scanFiles = [
            // SwaggerController 自身（包含 @OA\Info 等全局配置）
            $baseDir . '/controllers/SwaggerController.php',

            // v1 Controllers
            $baseDir . '/modules/v1/controllers/AuthController.php',
            $baseDir . '/modules/v1/controllers/CheckinController.php',
            $baseDir . '/modules/v1/controllers/CommonController.php',
            $baseDir . '/modules/v1/controllers/PhototypeController.php',
            $baseDir . '/modules/v1/controllers/PrivateController.php',
            $baseDir . '/modules/v1/controllers/PublicController.php',
            $baseDir . '/modules/v1/controllers/ServerController.php',
            $baseDir . '/modules/v1/controllers/SnapshotController.php',
            $baseDir . '/modules/v1/controllers/TagsController.php',
            $baseDir . '/modules/v1/controllers/VerseController.php',

            // v1 Models
            $baseDir . '/modules/v1/models/Code.php',
            $baseDir . '/modules/v1/models/File.php',
            $baseDir . '/modules/v1/models/Group.php',
            $baseDir . '/modules/v1/models/GroupSearch.php',
            $baseDir . '/modules/v1/models/GroupUser.php',
            $baseDir . '/modules/v1/models/GroupVerse.php',
            $baseDir . '/modules/v1/models/Manager.php',
            $baseDir . '/modules/v1/models/Meta.php',
            $baseDir . '/modules/v1/models/MetaCode.php',
            $baseDir . '/modules/v1/models/MetaQuery.php',
            $baseDir . '/modules/v1/models/Phototype.php',
            $baseDir . '/modules/v1/models/Property.php',
            $baseDir . '/modules/v1/models/RefreshToken.php',
            $baseDir . '/modules/v1/models/Resource.php',
            $baseDir . '/modules/v1/models/ResourceQuery.php',
            $baseDir . '/modules/v1/models/Snapshot.php',
            $baseDir . '/modules/v1/models/SnapshotSearch.php',
            $baseDir . '/modules/v1/models/Tags.php',
            $baseDir . '/modules/v1/models/TagsSearch.php',
            $baseDir . '/modules/v1/models/User.php',
            $baseDir . '/modules/v1/models/UserLinked.php',
            $baseDir . '/modules/v1/models/Verse.php',
            $baseDir . '/modules/v1/models/VerseCode.php',
            $baseDir . '/modules/v1/models/VerseProperty.php',
            $baseDir . '/modules/v1/models/VerseQuery.php',
            $baseDir . '/modules/v1/models/VerseSearch.php',
            $baseDir . '/modules/v1/models/VerseTags.php',
            $baseDir . '/modules/v1/models/Watermark.php',
            // v2 Controllers
            $baseDir . '/modules/v2/controllers/SnapshotController.php',
            $baseDir . '/modules/v2/controllers/TagsController.php',
            $baseDir . '/modules/v2/controllers/SystemController.php',
        ];

        // 文件存在性检查
        $existingFiles = array_filter($scanFiles, 'file_exists');

        if (empty($existingFiles)) {
            return [
                'error' => 'No files found to scan',
                'baseDir' => $baseDir,
                'attempted' => $scanFiles
            ];
        }

        $openapi = Generator::scan($existingFiles, ['validate' => false]);

        return json_decode($openapi->toJson());
    }
}
