# API 文档（v1）

本文档基于当前仓库代码梳理（Yii2 REST + `yii\rest\UrlRule`）。对应的 OpenAPI 文件见：

- [openapi-v1.yaml](openapi-v1.yaml)

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

只有 `PrivateController` 显式开启了 JWT Bearer 鉴权：

- [api/modules/v1/controllers/PrivateController.php](../../api/modules/v1/controllers/PrivateController.php)

请求头：

- `Authorization: Bearer <accessToken>`

获取 token：

- `POST /v1/auth/login` 或 `POST /v1/auth/refresh` 或 `POST /v1/auth/key-to-token`

注意：`VerseController`/`SnapshotController` 并未显式添加 authenticator（但内部查询里有 `Yii::$app->user->id` 的过滤逻辑）。如果你们期望这些接口必须登录才能用，建议后续统一加一个全局的 REST authenticator（这份文档先按现有代码记录，不擅自改行为）。

## 端点清单

下面列出所有在 [files/api/config/web.php](../../files/api/config/web.php) 里配置的 v1 端点，以及 Controller 实现情况。

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

说明：这些查询都会限制为关联 verse 拥有 `tags.key=public`。

### Checkin

- `GET /v1/checkin/list?page=1&expand=...`

说明：通过 `tags.key=checkin` 过滤 snapshot。

### Tags

- `GET /v1/tags`

说明：只返回 `type=Classify` 的 tag。

### Phototype

- `GET /v1/phototype/info?type=...`

说明：返回 `Phototype` 的 `id/data/title`，以及 `resource`（关联资源）。

### Snapshot

- `GET /v1/snapshot?page=1&tags=1,2,3&expand=...`
- `GET /v1/snapshot/public?page=1&tags=1,2,3&expand=...`（限制 `tags.key=public`）
- `GET /v1/snapshot/by-uuid?uuid=...&expand=...`
- `GET /v1/snapshot/by-verse-id?verse_id=...&expand=...`
- `GET /v1/snapshot/{id}?expand=...`（ActiveController 默认 view）

注意：该 Controller 禁用了 create/update/delete（对应 POST/PUT/PATCH/DELETE 不可用）。

### Verse

- `GET /v1/verse?page=1&tags=1,2,3&expand=...`（内部按 `author_id=Yii::$app->user->id` 过滤）
- `GET /v1/verse/public?page=1&tags=1,2,3&expand=...`（限制 `tags.key=public`）
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
