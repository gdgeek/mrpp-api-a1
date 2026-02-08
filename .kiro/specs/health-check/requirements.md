# 需求文档

## 简介

本文档定义了健康检查接口（Health Check API）的需求。该接口用于监控 Yii2 RESTful API 服务的运行状态，检查关键依赖服务（数据库、Redis）的连接状态，适用于 Docker/Kubernetes 健康探针场景。

## 术语表

- **Health_Check_Controller**: 处理健康检查请求的控制器
- **Health_Service**: 执行健康检查逻辑的服务组件
- **Health_Status**: 健康状态响应对象，包含服务状态信息
- **Dependency_Check**: 对单个依赖服务（如数据库、Redis）的连接检查

## 需求

### 需求 1：基础健康检查端点

**用户故事：** 作为运维人员，我希望有一个简单的健康检查端点，以便快速确认服务是否正常运行。

#### 验收标准

1. WHEN 客户端发送 GET 请求到 `/health` 端点 THEN Health_Check_Controller SHALL 返回 HTTP 200 状态码和健康状态信息
2. THE Health_Check_Controller SHALL 返回 JSON 格式的响应，包含 `status`、`timestamp` 和 `checks` 字段
3. WHEN 所有依赖服务正常 THEN Health_Status SHALL 将 `status` 字段设置为 `healthy`
4. WHEN 任一依赖服务异常 THEN Health_Status SHALL 将 `status` 字段设置为 `unhealthy` 并返回 HTTP 503 状态码

### 需求 2：数据库连接检查

**用户故事：** 作为运维人员，我希望健康检查能验证数据库连接状态，以便及时发现数据库连接问题。

#### 验收标准

1. WHEN 执行健康检查 THEN Health_Service SHALL 尝试连接 MySQL 数据库并执行简单查询
2. WHEN 数据库连接成功 THEN Dependency_Check SHALL 返回 `status: "up"` 和响应时间
3. IF 数据库连接失败 THEN Dependency_Check SHALL 返回 `status: "down"` 和错误信息
4. THE Health_Service SHALL 设置数据库检查超时时间为 5 秒

### 需求 3：Redis 连接检查

**用户故事：** 作为运维人员，我希望健康检查能验证 Redis 连接状态，以便及时发现缓存服务问题。

#### 验收标准

1. WHEN 执行健康检查 THEN Health_Service SHALL 尝试连接 Redis 并执行 PING 命令
2. WHEN Redis 连接成功且返回 PONG THEN Dependency_Check SHALL 返回 `status: "up"` 和响应时间
3. IF Redis 连接失败 THEN Dependency_Check SHALL 返回 `status: "down"` 和错误信息
4. THE Health_Service SHALL 设置 Redis 检查超时时间为 3 秒

### 需求 4：无认证访问

**用户故事：** 作为 Kubernetes 集群管理员，我希望健康检查端点无需认证即可访问，以便配置 liveness 和 readiness 探针。

#### 验收标准

1. THE Health_Check_Controller SHALL 允许未认证的请求访问健康检查端点
2. THE Health_Check_Controller SHALL 绕过 JWT 认证中间件
3. WHEN 请求不包含认证信息 THEN Health_Check_Controller SHALL 正常处理请求并返回健康状态

### 需求 5：响应格式规范

**用户故事：** 作为开发人员，我希望健康检查响应格式标准化，以便于解析和监控集成。

#### 验收标准

1. THE Health_Status SHALL 包含以下字段：`status`（字符串）、`timestamp`（ISO 8601 格式）、`checks`（对象）
2. THE Dependency_Check SHALL 包含以下字段：`status`（"up" 或 "down"）、`responseTime`（毫秒）
3. IF 检查失败 THEN Dependency_Check SHALL 额外包含 `error` 字段描述错误原因
4. THE Health_Check_Controller SHALL 设置响应头 `Content-Type: application/json`
