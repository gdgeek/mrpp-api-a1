<?php

namespace app\controllers;

use Yii;
use yii\rest\Controller;
use yii\web\Response;
use app\components\HealthService;

/**
 * HealthController 处理健康检查请求
 * 
 * 提供 /health 端点用于监控服务运行状态，
 * 适用于 Docker/Kubernetes 健康探针场景。
 * 
 * @property HealthService $healthService
 */
class HealthController extends Controller
{
    /**
     * @var HealthService 健康检查服务组件
     */
    private HealthService $_healthService;

    /**
     * 初始化控制器
     */
    public function init(): void
    {
        parent::init();
        
        // 获取 HealthService 组件，如果未注册则创建新实例
        if (Yii::$app->has('healthService')) {
            $this->_healthService = Yii::$app->healthService;
        } else {
            $this->_healthService = new HealthService();
        }
    }

    /**
     * 禁用认证，允许公开访问健康检查端点
     * 
     * 覆盖父类的 behaviors() 方法，移除所有认证相关的行为，
     * 确保健康检查端点可以被 Kubernetes 探针等无认证客户端访问。
     * 
     * @return array 行为配置数组
     */
    public function behaviors(): array
    {
        $behaviors = parent::behaviors();
        
        // 移除认证器（authenticator）以允许未认证访问
        unset($behaviors['authenticator']);
        
        // 设置响应格式为 JSON
        $behaviors['contentNegotiator']['formats']['application/json'] = Response::FORMAT_JSON;
        
        return $behaviors;
    }

    /**
     * GET /health - 返回服务健康状态
     * 
     * 调用 HealthService 执行所有健康检查，
     * 根据检查结果设置相应的 HTTP 状态码：
     * - 200 OK: 所有检查通过（status: "healthy"）
     * - 503 Service Unavailable: 任一检查失败（status: "unhealthy"）
     * 
     * @return array 健康状态响应，包含：
     *   - status: string 整体状态 "healthy" 或 "unhealthy"
     *   - timestamp: string ISO 8601 格式的检查时间
     *   - checks: array 各依赖服务的检查结果
     */
    public function actionIndex(): array
    {
        // 确保响应格式为 JSON
        Yii::$app->response->format = Response::FORMAT_JSON;
        
        // 设置 Content-Type 头
        Yii::$app->response->headers->set('Content-Type', 'application/json');
        
        // 执行健康检查
        $healthStatus = $this->_healthService->check();
        
        // 根据健康状态设置 HTTP 状态码
        if ($healthStatus['status'] === 'unhealthy') {
            Yii::$app->response->statusCode = 503;
        } else {
            Yii::$app->response->statusCode = 200;
        }
        
        return $healthStatus;
    }

    /**
     * 设置 HealthService（用于测试）
     * 
     * @param HealthService $healthService 健康检查服务实例
     */
    public function setHealthService(HealthService $healthService): void
    {
        $this->_healthService = $healthService;
    }
}
