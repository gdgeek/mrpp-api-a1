<?php

namespace app\components;

use Yii;
use yii\base\Component;

/**
 * HealthService 组件
 * 
 * 执行各项健康检查，聚合检查结果。
 * 用于监控 Yii2 RESTful API 服务的运行状态，
 * 检查关键依赖服务（数据库、Redis）的连接状态。
 */
class HealthService extends Component
{
    /**
     * 数据库检查超时时间（秒）
     */
    public const DATABASE_TIMEOUT = 5;

    /**
     * Redis 检查超时时间（秒）
     */
    public const REDIS_TIMEOUT = 3;

    /**
     * 执行所有健康检查
     * 
     * @return array 健康状态结果，包含：
     *   - status: string 整体状态 "healthy" 或 "unhealthy"
     *   - timestamp: string ISO 8601 格式的检查时间
     *   - checks: array 各依赖服务的检查结果
     */
    public function check(): array
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
        ];

        // 聚合健康状态：所有检查都为 "up" 时为 "healthy"，否则为 "unhealthy"
        $isHealthy = true;
        foreach ($checks as $check) {
            if ($check['status'] === 'down') {
                $isHealthy = false;
                break;
            }
        }

        return [
            'status' => $isHealthy ? 'healthy' : 'unhealthy',
            'timestamp' => date('c'), // ISO 8601 格式
            'checks' => $checks,
        ];
    }

    /**
     * 检查数据库连接
     * 
     * 尝试连接 MySQL 数据库并执行简单查询。
     * 超时时间为 5 秒。
     * 
     * @return array 检查结果，包含：
     *   - status: string "up" 或 "down"
     *   - responseTime: int 响应时间（毫秒）
     *   - error: string 错误信息（仅失败时）
     */
    protected function checkDatabase(): array
    {
        $startTime = microtime(true);

        try {
            $db = Yii::$app->db;

            // 确保数据库连接已打开
            if (!$db->isActive) {
                $db->open();
            }

            // 执行简单查询验证连接
            $db->createCommand('SELECT 1')->queryScalar();

            $responseTime = $this->calculateResponseTime($startTime);

            return [
                'status' => 'up',
                'responseTime' => $responseTime,
            ];
        } catch (\Exception $e) {
            $responseTime = $this->calculateResponseTime($startTime);

            return [
                'status' => 'down',
                'responseTime' => $responseTime,
                'error' => $this->formatErrorMessage($e),
            ];
        }
    }

    /**
     * 检查 Redis 连接
     * 
     * 尝试连接 Redis 并执行 PING 命令。
     * 超时时间为 3 秒。
     * 
     * @return array 检查结果，包含：
     *   - status: string "up" 或 "down"
     *   - responseTime: int 响应时间（毫秒）
     *   - error: string 错误信息（仅失败时）
     */
    protected function checkRedis(): array
    {
        $startTime = microtime(true);

        try {
            $redis = Yii::$app->redis;

            // 设置连接超时（yii2-redis 使用 connectionTimeout 属性）
            $redis->connectionTimeout = self::REDIS_TIMEOUT;
            $redis->dataTimeout = self::REDIS_TIMEOUT;

            // 执行 PING 命令验证连接
            $response = $redis->executeCommand('PING');

            $responseTime = $this->calculateResponseTime($startTime);

            // 验证 PING 响应是否为 PONG
            if ($response === 'PONG' || $response === true) {
                return [
                    'status' => 'up',
                    'responseTime' => $responseTime,
                ];
            }

            return [
                'status' => 'down',
                'responseTime' => $responseTime,
                'error' => 'Unexpected PING response',
            ];
        } catch (\Exception $e) {
            $responseTime = $this->calculateResponseTime($startTime);

            return [
                'status' => 'down',
                'responseTime' => $responseTime,
                'error' => $this->formatErrorMessage($e),
            ];
        }
    }

    /**
     * 计算响应时间
     * 
     * @param float $startTime 开始时间（microtime）
     * @return int 响应时间（毫秒）
     */
    protected function calculateResponseTime(float $startTime): int
    {
        $endTime = microtime(true);
        $responseTimeMs = ($endTime - $startTime) * 1000;

        return (int) round($responseTimeMs);
    }

    /**
     * 格式化错误信息
     * 
     * 根据异常类型返回用户友好的错误信息。
     * 
     * @param \Exception $e 异常对象
     * @return string 格式化后的错误信息
     */
    protected function formatErrorMessage(\Exception $e): string
    {
        $message = $e->getMessage();

        // 检测常见错误类型并返回友好信息
        if (stripos($message, 'timeout') !== false || stripos($message, 'timed out') !== false) {
            return 'Connection timed out';
        }

        if (stripos($message, 'authentication') !== false || stripos($message, 'auth') !== false) {
            return 'Authentication failed';
        }

        if (stripos($message, 'unknown database') !== false || stripos($message, 'database not found') !== false) {
            return 'Database not found';
        }

        if (stripos($message, 'network') !== false || stripos($message, 'unreachable') !== false) {
            return 'Network unreachable';
        }

        if (stripos($message, 'connection refused') !== false) {
            return 'Service unavailable';
        }

        // 返回原始错误信息（截断过长的信息）
        return strlen($message) > 100 ? substr($message, 0, 100) . '...' : $message;
    }
}
