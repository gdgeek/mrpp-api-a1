---
inclusion: manual
---

# Yii2 健康检查接口实现技能

为 Yii2 RESTful API 项目添加健康检查接口（`GET /health`），支持 Docker/Kubernetes 健康探针。

## 实现步骤

1. 创建 `components/HealthService.php` - 检查 MySQL 和 Redis 连接状态
2. 创建 `controllers/HealthController.php` - 无认证访问，返回 JSON
3. 在 `config/web.php` 中注册组件和路由
4. （可选）在 `docker-compose.yml` 中配置 healthcheck

## 详细实现参考

参考文档：#[[file:docs/HEALTH_CHECK.md]]

## 关键注意事项

- 数据库检查前必须确保连接已打开（`$db->isActive` / `$db->open()`），否则 `$db->pdo` 为 null
- 控制器需要 `unset($behaviors['authenticator'])` 禁用认证
- 健康时返回 HTTP 200，不健康时返回 HTTP 503
- 如果项目没有 Redis，移除 `checkRedis()` 相关代码
- 记得同时更新 `files/` 目录下的配置文件（如果项目使用 Docker 构建覆盖）
- 记得更新 OpenAPI/Swagger 文档
