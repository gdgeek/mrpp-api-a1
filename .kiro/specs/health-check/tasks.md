# 实现计划：健康检查接口

## 概述

本计划将健康检查接口的设计分解为可执行的编码任务，按照增量方式实现，确保每个步骤都能验证核心功能。

## 任务

- [x] 1. 创建 HealthService 组件
  - [x] 1.1 创建 `components/HealthService.php` 文件
    - 实现 `check()` 方法返回聚合的健康状态
    - 实现 `checkDatabase()` 方法检查 MySQL 连接
    - 实现 `checkRedis()` 方法检查 Redis 连接
    - 实现响应时间计算逻辑
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 3.1, 3.2, 3.3, 3.4_
  
  - [ ]* 1.2 编写 HealthService 属性测试
    - **Property 1: 健康状态聚合正确性**
    - **Validates: Requirements 1.3, 1.4**
  
  - [ ]* 1.3 编写 HealthService 单元测试
    - 测试数据库连接成功/失败场景
    - 测试 Redis 连接成功/失败场景
    - _Requirements: 2.2, 2.3, 3.2, 3.3_

- [x] 2. 创建 HealthController 控制器
  - [x] 2.1 创建 `controllers/HealthController.php` 文件
    - 继承 `yii\rest\Controller`
    - 覆盖 `behaviors()` 方法禁用认证
    - 实现 `actionIndex()` 方法调用 HealthService
    - 根据健康状态设置正确的 HTTP 状态码
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 4.1, 4.2, 4.3, 5.4_
  
  - [ ]* 2.2 编写 HealthController 属性测试
    - **Property 2: 响应格式完整性**
    - **Validates: Requirements 1.2, 5.1**
  
  - [ ]* 2.3 编写 HealthController 属性测试
    - **Property 3: 依赖检查结果格式正确性**
    - **Validates: Requirements 2.2, 2.3, 3.2, 3.3, 5.2, 5.3**

- [x] 3. 配置路由和组件注册
  - [x] 3.1 更新 `config/web.php` 配置文件
    - 在 urlManager rules 中添加 `GET health => health/index` 路由
    - 在 components 中注册 HealthService 组件
    - _Requirements: 1.1_

- [x] 4. 检查点 - 确保所有测试通过
  - 确保所有测试通过，如有问题请询问用户。

- [x] 5. 集成测试和验证
  - [ ]* 5.1 编写集成测试
    - 测试 `/health` 端点无认证访问
    - 测试响应格式和 Content-Type
    - 测试健康/不健康状态的 HTTP 状态码
    - _Requirements: 1.1, 4.1, 4.3, 5.4_

- [x] 6. 最终检查点 - 确保所有测试通过
  - 确保所有测试通过，如有问题请询问用户。

## 备注

- 标记 `*` 的任务为可选任务，可跳过以加快 MVP 开发
- 每个任务都引用了具体的需求以便追溯
- 检查点确保增量验证
- 属性测试验证通用正确性属性
- 单元测试验证具体示例和边界情况
