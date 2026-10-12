# API 文档（A1 / Yii2）

本文档基于当前仓库代码梳理（Yii2 REST + `yii\rest\UrlRule`）。对应的 OpenAPI 文件见：

- [openapi-v1.yaml](openapi-v1.yaml)

## 当前部署与历史接口

Docker 构建把 [files/api/config/web.php](../../files/api/config/web.php) 复制为运行配置（见 [Dockerfile](../../dockerfile/Dockerfile)）。该配置启用 `/v1/server/*` 与 `/v2/snapshots`；下文记录的旧 `/v1/public`、`/v1/private`、`/v1/snapshot`、`/v1/verse` 等路由规则在配置的注释块中，不能据此认为线上已开放。本次不改变路由开关。

## 公开属性与分类筛选

公开只由场景关联的 `property.key=public` 决定（`verse_property`），与分类标签独立。`tags.key=public` 已停用：标签目录隐藏该记录，传入它的 ID 也不会按公开属性解释或作为分类命中。仅传旧 public 标签 ID 返回空列表；与普通分类 ID 混传时，仅普通分类参与筛选。其他分类之间保持 OR，同一个场景只返回一次。

当前列表入口：

- `GET /v1/server/public`：公开属性范围，匿名可用。
- `GET /v1/server/checkin`：打卡属性范围，匿名可用。
- `GET /v1/server/private`：当前作者范围，需 Bearer 登录。
- `GET /v1/server/group`：当前用户组范围，需 Bearer 登录。
- `GET /v2/snapshots?scope=public|checkin|private|group`：对应上述范围；private/group 仍要求登录。

这些列表支持独立 `public=1` 或 `public=true`（存在公开属性）、`public=0` 或 `public=false`（没有公开属性），不传则保留原范围。参数与 `tags=2,3` 分类、作者/组范围取交集；公开范围再传 `public=0` 返回空列表。`public=`、`public=2`、`public[]=1` 等非法值返回 HTTP 400，不会自动转换为布尔值。Yii2 结果缓存包含生成的 SQL 和绑定参数，不同公开条件不会共享列表结果。

例如：`GET /v1/server/private?public=1&tags=2,3&expand=id,verse_id` 查询当前作者已公开且属于分类 2 或 3 的快照。旧控制器中复用 SnapshotSearch / VerseSearch 的列表同样支持该筛选；旧 Public 详情始终要求公开属性，缺少属性或标识不存在时保留 HTTP 400。Verse 的查看策略允许属性公开或当前作者，旧 status/public 标签不授予查看权限。

## 基础信息

- 路由前缀：`/v1`
- Content-Type：多数接口使用 JSON（配置里 `request.parsers` 支持 `application/json`）
- 分页：返回 `ActiveDataProvider` 的接口，分页信息会通过响应头返回：
  - `X-Pagination-Total-Count`
  - `X-Pagination-Page-Count`
  - `X-Pagination-Current-Page`
  - `X-Pagination-Per-Page`

## 很重要：`expand`（否则可能拿不到字段）

当前项目里 `Verse` 和 `Snapshot` model 的 `fields()` 返回空数组：

- [api/modules/v1/models/Verse.php](../../api/modules/v1/models/Verse.php)
- [api/modules/v1/models/Snapshot.php](../../api/modules/v1/models/Snapshot.php)

这意味着：

- 不传 `expand` 时，序列化结果可能是空对象 `{}`
- 需要使用 `expand` 参数显式展开 `extraFields()` 里的字段

常用示例：

- Verse：`GET /v1/verse?expand=id,name,uuid,description,metas,resources,image,managers,code`
- Snapshot：`GET /v1/snapshot?expand=id,uuid,verse_id,code,data,metas,resources,author,author_id,image,name,description,managers`

## 鉴权

当前 Server 的 private/group 使用 JWT Bearer 鉴权，V2 的 private/group 要求登录。历史 `PrivateController` 也显式开启 JWT Bearer 鉴权：

- [api/modules/v1/controllers/PrivateController.php](../../api/modules/v1/controllers/PrivateController.php)

请求头：

- `Authorization: Bearer <accessToken>`

获取 token：

- `POST /v1/auth/login` 或 `POST /v1/auth/refresh` 或 `POST /v1/auth/key-to-token`

注意：`VerseController`/`SnapshotController` 并未显式添加 authenticator（但内部查询里有 `Yii::$app->user->id` 的过滤逻辑）。如果你们期望这些接口必须登录才能用，建议后续统一加一个全局的 REST authenticator（这份文档先按现有代码记录，不擅自改行为）。

## 端点清单

下面记录 v1 Controller 实现；历史路由是否启用以上述部署配置为准。

### Meta（非 v1）

- `GET /apple-app-site-association`
  - 用于 Apple Universal Links / AASA 文件
  - 注意：配置里路由指向 `site/apple-app-site-association`，但当前仓库 [api/controllers/SiteController.php](../../api/controllers/SiteController.php) 没有实现对应 action，实际运行可能 404（取决于线上是否有额外配置/覆盖）。

### Auth

- `POST /v1/auth/login`
  - body: `{ "username": "...", "password": "..." }`
  - resp: `{ success, message, nickname, token: { accessToken, expires, refreshToken } }`

- `POST /v1/auth/refresh`
  - body: `{ "refreshToken": "..." }`
  - resp 同上

- `POST /v1/auth/key-to-token`
  - body: `{ "key": "..." }`
  - resp 同上

### Common

- `GET /v1/common/test`
  - 返回 Redis `INFO`（结构不固定）

- `POST /v1/common/verify`
  - 当前实现为固定返回：`{ success: true, data: { watermark: false, shutdown: false } }`

- `POST /v1/common/report`
  - body: `{ "refreshToken": "..." }`
  - 注意：此接口内部 try/catch，失败也会返回 200，但 `success=false`

- `POST /v1/common/watermark`
  - 注意：路由规则里配置了，但当前 `CommonController` 没有实现 `actionWatermark()`，运行时会 404

### Private（需要 Bearer JWT）

- `GET /v1/private/by-uuid?uuid=...&expand=...`
- `GET /v1/private/by-id?id=...&expand=...`
- `GET /v1/private/by-verse-id?verse_id=...&expand=...`
- `GET /v1/private/list?page=1&pageSize=15&tags=1,2,3&expand=...`

说明：这些查询都会限制为“当前登录用户自己的 verse”。

### Public

- `GET /v1/public/by-uuid?uuid=...&expand=...`
- `GET /v1/public/by-id?id=...&expand=...`
- `GET /v1/public/by-verse-id?verse_id=...&expand=...`
- `GET /v1/public/list?page=1&pageSize=15&tags=1,2,3&expand=...`

说明：这些查询都会限制为关联 verse 拥有 `property.key=public`。

### Checkin

- `GET /v1/checkin/list?page=1&expand=...`

说明：通过 `tags.key=checkin` 过滤 snapshot。

### Tags

- `GET /v1/tags`

说明：不按 `type` 筛选，返回所有 tag，并排除旧 `key=public` 标签。

### Phototype

- `GET /v1/phototype/info?type=...`

说明：返回 `Phototype` 的 `id/data/title`，以及 `resource`（关联资源）。

### Snapshot

- `GET /v1/snapshot?page=1&tags=1,2,3&expand=...`
- `GET /v1/snapshot/public?page=1&tags=1,2,3&expand=...`（限制 `property.key=public`）
- `GET /v1/snapshot/by-uuid?uuid=...&expand=...`
- `GET /v1/snapshot/by-verse-id?verse_id=...&expand=...`
- `GET /v1/snapshot/{id}?expand=...`（ActiveController 默认 view）

注意：该 Controller 禁用了 create/update/delete（对应 POST/PUT/PATCH/DELETE 不可用）。

### Verse

- `GET /v1/verse?page=1&tags=1,2,3&expand=...`（内部按 `author_id=Yii::$app->user->id` 过滤）
- `GET /v1/verse/public?page=1&tags=1,2,3&expand=...`（限制 `property.key=public`）
- `GET /v1/verse/open?page=1&pageSize=15&expand=...`（存在 `verse_open` 记录）
- `GET /v1/verse/release?code=...&expand=...`（返回单个 verse）
- `GET /v1/verse/{id}?expand=...`（ActiveController 默认 view）

注意：该 Controller 禁用了 create/update/delete（对应 POST/PUT/PATCH/DELETE 不可用）。

## cURL 示例

登录：

```bash
curl -s -X POST http://localhost/v1/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"username":"demo","password":"demo"}'
```

使用 token 调用私有列表：

```bash
curl -s 'http://localhost/v1/private/list?page=1&pageSize=15&expand=id,uuid,verse_id' \
  -H 'Authorization: Bearer <accessToken>'
```

取公开列表：

```bash
curl -s 'http://localhost/v1/public/list?page=1&pageSize=15&expand=id,uuid,verse_id'
```


## 隔离回归测试

此测试仅使用 SQLite 内存数据库，不加载业务配置。已安装项目依赖时：

```bash
cd api
php vendor/bin/phpunit -c tests/public-filter.phpunit.xml
```

也可通过 `A1_TEST_VENDOR=/absolute/path/to/vendor` 复用已安装 Yii2 与 PHPUnit 的依赖目录。覆盖属性公开、旧标签停用、分类 OR/公开 AND、作者/组边界、缓存、HTTP 400、公开详情和 VersePolicy。

## 独立 Identity 兼容桥

默认 `IDENTITY_DATA_SOURCE=legacy`，保留既有登录、刷新和 HTML 页面行为。独立模式必须显式配置 `IDENTITY_DATA_SOURCE=identity`、`IDENTITY_INDEPENDENT_READY=true`、Identity 内网 URL 和内部 Auth/IAM 密钥；缺失、无效配置或上游不可用均拒绝请求。配置模板见仓库根 `.env.identity.example`，密钥只进入服务端部署环境。

原 `POST /v1/auth/login`、`refresh`、`key-to-token` 以及 `POST /v1/common/report` 保留消息、昵称、token 外观。账户状态、角色、密码及令牌签发只调用 Identity；Bearer 不经本地 HS256 或旧 `user` 验证。场景作者/组等业务对象权限仍在本服务判断。`common/report` 原生模式只轮换一次令牌。新增 `POST /v1/auth/logout` 只在独立模式开放，撤销 Identity 会话。

内部刷新固定发送 `consumer: "a1"`、`allowLoginCode: false`，浏览器同名字段不能覆盖。二维码兑换使用 `/internal/yii3/login-code` 的 `redeem`，短码 60 秒、多设备兑换的实际期限和每次签发由 Identity 决定；不会回读业务 `user_linked` 或 Redis。旧 access 需事先审核实际 HS256 key、issuer 与来源，导入 Identity 信任清单；不会因本地旧签名通过而绕过当前账户状态。`IdentityJwtHttpBearerAuth` 经 bootstrap 同时覆盖 v1 与 v2 的 CompositeAuth。

HTML 登录通过 Identity 验证，令牌仅放在服务端 PHP session，之后每请求验证/必要时轮换。受限旧 Yii 签名 cookie/session 过渡需 Identity 配置 `IDENTITY_LEGACY_PHP_SESSION_ACCEPT_UNTIL`，并校验原 auth_key；不能仅凭 user ID 登录。桥接保持旧 cookie 原样，不续期。Cookie 的旧 payload 无原签发时间，服务器无法反推出其绝对到期，浏览器原期限与明确桥接截止共同约束。网络 503 保留服务端令牌但拒绝本次请求；退出与在途轮换不能恢复已撤销 family。

上线前必须盘点共享 Redis 命名空间。A1 `RefreshToken`（Yii Redis AR）只写 `id,user_id,key`，明文 key，无 issuedAt、应用 expiry 或 Redis TTL；消费者 exact key 查找，不读取 `expires_at/revoked_at`。主 PHP 同名 AR 可能写带 `created_at/expires_at/session_id` 的 SHA256 key，消费者先 hash(raw)、再兼容明文并检查应用 expiry/撤销；Yii3 则写 `refresh_token:<token>` 字符串，SETEX 30 天。三者不能仅凭前缀合并。原生桥不自动导入、不推测 key encoding 或补 30 天期限：共享 AR 需审核后的 manifest，保留原 expiry；仅明确 plaintext 且审核 A1 exact-lookup 的记录可标 `a1Compatible`，由 Identity 对固定 A1 consumer 保持其原消费者语义。首次兑换绑定 native family，短时幂等重试之外不再新建；改密/禁用/退出及撤销 tombstone 防止旧凭据复活。

独立认证回归不连接业务 DB/Redis，包含实际回环 HTTP transport、Auth/IAM 分钥、旧接口外观、默认 legacy、拒绝本地 writer、PHP 签名 cookie、503 与退出竞争：

```bash
cd api
php vendor/bin/phpunit -c tests/identity-authority.phpunit.xml
```

CI 使用已锁定 Composer 依赖和原有 unit/public-filter 测试。`workflow_dispatch.publish_candidate` 默认 false；显式 true 或精确 `codex/identity-online-dev-20261012` 分支 push 仅在全部测试成功后发布 `a1:candidate-<完整SHA>` 的 linux/amd64 镜像与 digest 证明，不更新 develop/latest/publish，不部署任何环境。新镜像构建使用 `composer install --no-scripts`，不会执行迁移或凭据退役。
