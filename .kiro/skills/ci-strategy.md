---
inclusion: fileMatch
fileMatchPattern: '**/.github/workflows/*.yml'
---

# CI/CD 构建策略

## 整体架构

项目采用 GitHub Actions 可复用工作流（reusable workflows）架构，主入口为 `docker-publish.yml`，按顺序调用：
1. `run-tests.yml` — 运行测试
2. `build-push.yml` — 构建并推送 Docker 镜像
3. `deploy-notify.yml` — 部署通知

## 触发条件

- `push` 到 `main` 分支：完整流水线（测试 → 构建 → 部署）
- `push` 到 `develop` 分支：测试 → 构建推送镜像（tag 为 `develop` 和 `短hash`），不触发部署
- `pull_request` 到 `main` 分支：仅测试和构建，不触发部署

## 测试阶段（run-tests.yml）

- PHP 版本：8.5
- 扩展：mbstring, intl, pdo, pdo_sqlite
- 使用 SQLite 内存数据库替代真实数据库
- 运行 `composer audit` 安全审计
- 运行 `vendor/bin/codecept run unit` 单元测试
- Prepare Config 步骤会从 `files/` 目录复制测试配置，并生成 SQLite 内存数据库配置覆盖 `test_db.php`

## Docker 镜像构建规则（build-push.yml）

镜像同时推送到两个仓库：
- GHCR：`ghcr.io/<github_repository>`
- 腾讯云：`hkccr.ccs.tencentyun.com/gdgeek/a1`

### Tag 策略（由 docker/metadata-action 生成）
- `分支名`（如 main）— 所有 push 触发
- `短hash`（7位 commit SHA）— 所有 push 触发
- `latest` — 仅默认分支（main）push 时附加

### Dockerfile 路径
- `dockerfile/Dockerfile`

## 所需 Secrets

| Secret | 用途 |
|--------|------|
| `TENCENT_CLOUD_USERNAME` | 腾讯云镜像仓库用户名 |
| `TENCENT_CLOUD_PASSWORD` | 腾讯云镜像仓库密码 |

## 注意事项

- 分支名中的 `/` 会被 metadata-action 替换为 `-`（如 `feature/foo` → `feature-foo`）
- CI 测试使用 SQLite 内存数据库，不依赖外部 MySQL/Redis 服务
- PR 不会推送镜像（`push: github.event_name != 'pull_request'`）
- 如需扩展到其他分支（如 develop），需修改 `docker-publish.yml` 的触发条件
