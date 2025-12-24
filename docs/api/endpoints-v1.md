# v1 API 端点速查（人读版）

这是一个“快速翻阅用”的端点表格。

- 机器可读：见 [openapi-v1.yaml](openapi-v1.yaml)
- 说明文档：见 [README.md](README.md)

## 约定

- `expand`：推荐在 Verse/Snapshot 相关接口都带上（否则可能返回 `{}`）。
- 分页：多数列表接口支持 `page`；部分支持 `pageSize`；分页信息在响应头 `X-Pagination-*`。
- `tags`：以逗号分隔的 tag id，例如 `1,2,3`。

## Meta（非 v1）

| 方法 | 路径 | 鉴权 | 参数 | 备注 |
|---|---|---|---|---|
| GET | `/apple-app-site-association` | 否 | - | 配置存在，但当前代码可能未实现（可能 404） |

## Auth

| 方法 | 路径 | 鉴权 | 参数 | 备注 |
|---|---|---|---|---|
| POST | `/v1/auth/login` | 否 | body: `username`, `password` | 返回 `token{accessToken,expires,refreshToken}` |
| POST | `/v1/auth/refresh` | 否 | body: `refreshToken` | 刷新 token |
| POST | `/v1/auth/key-to-token` | 否 | body: `key` | linked key 换 token |

## Common

| 方法 | 路径 | 鉴权 | 参数 | 备注 |
|---|---|---|---|---|
| GET | `/v1/common/test` | 否 | - | Redis INFO |
| POST | `/v1/common/verify` | 否 | - | 当前实现为固定返回 |
| POST | `/v1/common/report` | 否 | body: `refreshToken` | 失败也可能返回 200（`success=false`） |
| POST | `/v1/common/watermark` | 否 | - | 路由配置存在，但 controller 未实现（可能 404） |

## Private（需要 Bearer JWT）

| 方法 | 路径 | 鉴权 | 参数 | 备注 |
|---|---|---|---|---|
| GET | `/v1/private/by-uuid` | Bearer | `uuid`, `expand` | 仅本人 verse |
| GET | `/v1/private/by-id` | Bearer | `id`, `expand` | 仅本人 verse |
| GET | `/v1/private/by-verse-id` | Bearer | `verse_id`, `expand` | 仅本人 verse |
| GET | `/v1/private/list` | Bearer | `page`, `pageSize`, `tags`, `expand` | 列表 + 分页 |

## Public

| 方法 | 路径 | 鉴权 | 参数 | 备注 |
|---|---|---|---|---|
| GET | `/v1/public/by-uuid` | 否 | `uuid`, `expand` | 仅 public tag |
| GET | `/v1/public/by-id` | 否 | `id`, `expand` | 仅 public tag |
| GET | `/v1/public/by-verse-id` | 否 | `verse_id`, `expand` | 仅 public tag |
| GET | `/v1/public/list` | 否 | `page`, `pageSize`, `tags`, `expand` | 列表 + 分页 |

## Checkin

| 方法 | 路径 | 鉴权 | 参数 | 备注 |
|---|---|---|---|---|
| GET | `/v1/checkin/list` | 否 | `page`, `expand` | `tags.key=checkin` 过滤 |

## Tags

| 方法 | 路径 | 鉴权 | 参数 | 备注 |
|---|---|---|---|---|
| GET | `/v1/tags` | 否 | `id?`, `name?`, `key?` | 固定筛选 `type=Classify` |

## Phototype

| 方法 | 路径 | 鉴权 | 参数 | 备注 |
|---|---|---|---|---|
| GET | `/v1/phototype/info` | 否 | `type`, `expand` | 返回 `id/title/data/resource` |

## Snapshot

| 方法 | 路径 | 鉴权 | 参数 | 备注 |
|---|---|---|---|---|
| GET | `/v1/snapshot` | 否 | `page`, `tags`, `expand` | 列表 |
| GET | `/v1/snapshot/public` | 否 | `page`, `tags`, `expand` | 仅 public tag |
| GET | `/v1/snapshot/by-uuid` | 否 | `uuid`, `expand` | 单个 |
| GET | `/v1/snapshot/by-verse-id` | 否 | `verse_id`, `expand` | 单个 |
| GET | `/v1/snapshot/{id}` | 否 | `id(path)`, `expand` | 默认 view |

## Verse

| 方法 | 路径 | 鉴权 | 参数 | 备注 |
|---|---|---|---|---|
| GET | `/v1/verse` |（见备注）| `page`, `tags`, `expand` | controller 内按 `author_id=Yii::$app->user->id` 过滤 |
| GET | `/v1/verse/public` | 否 | `page`, `tags`, `expand` | 仅 public tag |
| GET | `/v1/verse/open` | 否 | `page`, `pageSize`, `expand` | 存在 `verse_open` |
| GET | `/v1/verse/release` | 否 | `code`, `expand` | 返回单个 verse |
| GET | `/v1/verse/{id}` | 否 | `id(path)`, `expand` | 默认 view |
