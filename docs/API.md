# API 接口文档

> 更新时间: 2025-12-25

## 基础信息

- **API 版本**: v1
- **认证方式**: JWT Bearer Token（部分接口需要认证）
- **响应格式**: JSON

---

## 认证接口 (v1/auth)

### 1. 登录

| 属性     | 值                    |
| -------- | --------------------- |
| **路由** | `POST /v1/auth/login` |
| **认证** | 不需要                |

**请求参数 (Body - POST)**:

| 参数名     | 类型   | 必填 | 说明   |
| ---------- | ------ | ---- | ------ |
| `username` | string | ✅   | 用户名 |
| `password` | string | ✅   | 密码   |

**响应示例**:

```json
{
  "success": true,
  "message": "login",
  "nickname": "用户昵称",
  "token": {
    "accessToken": "JWT access token",
    "refreshToken": "刷新token"
  }
}
```

**错误响应**:

- `400 Bad Request`: `username is required` / `password is required` / `no user` / `wrong password`

---

### 2. 刷新 Token

| 属性     | 值                      |
| -------- | ----------------------- |
| **路由** | `POST /v1/auth/refresh` |
| **认证** | 不需要                  |

**请求参数 (Body - POST)**:

| 参数名         | 类型   | 必填 | 说明     |
| -------------- | ------ | ---- | -------- |
| `refreshToken` | string | ✅   | 刷新令牌 |

**响应示例**:

```json
{
  "success": true,
  "message": "refresh",
  "nickname": "用户昵称",
  "token": {
    "accessToken": "新的 JWT access token",
    "refreshToken": "新的刷新token"
  }
}
```

**错误响应**:

- `400 Bad Request`: `refreshToken is required` / `no user` / `save error`

---

## 服务接口 (v1/server)

### 3. 测试接口

| 属性     | 值                                              |
| -------- | ----------------------------------------------- |
| **路由** | `GET /v1/server/test` 或 `HEAD /v1/server/test` |
| **认证** | 不需要                                          |

**响应**: `"test"` (纯字符串)

---

### 4. 获取公开场景列表

| 属性     | 值                      |
| -------- | ----------------------- |
| **路由** | `GET /v1/server/public` |
| **认证** | 不需要                  |
| **缓存** | 30 秒                   |

**请求参数 (Query)**:

| 参数名     | 类型   | 必填 | 默认值 | 说明                                |
| ---------- | ------ | ---- | ------ | ----------------------------------- |
| `pageSize` | int    | ❌   | 15     | 每页数量                            |
| `page`     | int    | ❌   | 1      | 页码                                |
| `expand`   | string | ❌   | -      | 扩展字段，用逗号分隔                |
| `tags`     | string | ❌   | -      | 标签 ID，多个用逗号分隔，如 `1,2,3` |

**可用 expand 字段**:

| 字段          | 类型   | 说明         |
| ------------- | ------ | ------------ |
| `id`          | int    | 快照 ID      |
| `name`        | string | 场景名称     |
| `description` | string | 场景描述     |
| `image`       | object | 封面图片对象 |
| `author_id`   | int    | 作者 ID      |
| `author`      | object | 作者信息对象 |
| `uuid`        | string | UUID         |
| `verse_id`    | int    | 场景 ID      |
| `code`        | string | 场景代码     |
| `data`        | object | 场景数据     |
| `metas`       | array  | 元数据列表   |
| `resources`   | array  | 资源列表     |
| `managers`    | array  | 管理者列表   |

**请求示例**:

```
GET /v1/server/public?expand=id,name,description,image&pageSize=10&page=1
GET /v1/server/public?expand=id,name,image&tags=1,2,3
```

**响应示例** (数组):

```json
[
  {
    "id": 1,
    "name": "场景名称",
    "description": "场景描述",
    "image": {
      "id": 100,
      "url": "https://example.com/image.jpg"
    }
  }
]
```

**响应头** (分页信息):

| Header                      | 说明     |
| --------------------------- | -------- |
| `X-Pagination-Total-Count`  | 总数     |
| `X-Pagination-Page-Count`   | 总页数   |
| `X-Pagination-Current-Page` | 当前页   |
| `X-Pagination-Per-Page`     | 每页数量 |

---

### 5. 获取打卡场景列表

| 属性     | 值                       |
| -------- | ------------------------ |
| **路由** | `GET /v1/server/checkin` |
| **认证** | 不需要                   |

**请求参数 (Query)**: 同 `public` 接口（支持分页和 expand）

**说明**: 返回标记为 `checkin` 属性的场景快照列表

---

### 6. 获取单个快照

| 属性     | 值                        |
| -------- | ------------------------- |
| **路由** | `GET /v1/server/snapshot` |
| **认证** | 不需要                    |
| **缓存** | 30 秒                     |

**请求参数 (Query)**:

| 参数名     | 类型   | 必填   | 说明                 |
| ---------- | ------ | ------ | -------------------- |
| `id`       | int    | 二选一 | 快照 ID              |
| `verse_id` | int    | 二选一 | 场景 ID              |
| `expand`   | string | ❌     | 扩展字段，用逗号分隔 |

> ⚠️ `id` 和 `verse_id` 必须提供其中一个

**请求示例**:

```
GET /v1/server/snapshot?id=1&expand=id,name,description,code,data,metas,resources
GET /v1/server/snapshot?verse_id=10&expand=id,name,image
```

**响应示例** (单个对象):

```json
{
  "id": 1,
  "name": "场景名称",
  "description": "场景描述",
  "code": "...",
  "data": {...},
  "metas": [...],
  "resources": [...]
}
```

**错误响应**:

- `400 Bad Request`: `id or verse_id is required.` / `Snapshot not found.`

---

### 7. 获取所有标签

| 属性     | 值                    |
| -------- | --------------------- |
| **路由** | `GET /v1/server/tags` |
| **认证** | 不需要                |
| **缓存** | 30 秒                 |

**请求参数 (Query)**: 支持分页参数 (`pageSize`, `page`)

**响应示例** (数组):

```json
[
  {
    "id": 1,
    "name": "标签名称",
    "key": "tag_key"
  }
]
```

**说明**: 不按 `type` 筛选，返回所有标签并排除已停用的 `key=public` 标签。

---

### 8. 获取私有场景列表 🔒

| 属性     | 值                       |
| -------- | ------------------------ |
| **路由** | `GET /v1/server/private` |
| **认证** | ✅ 需要 JWT Token        |
| **缓存** | 30 秒                    |

**请求参数 (Query)**: 同 `public` 接口（支持 `pageSize`, `page`, `expand`, `tags`）

**说明**: 返回当前登录用户创建的场景

**错误响应**:

- `401 Unauthorized`: 未提供有效 Token

---

### 9. 获取组场景列表 🔒

| 属性     | 值                     |
| -------- | ---------------------- |
| **路由** | `GET /v1/server/group` |
| **认证** | ✅ 需要 JWT Token      |
| **缓存** | 30 秒                  |

**请求参数 (Query)**:

| 参数名     | 类型 | 必填 | 默认值 | 说明     |
| ---------- | ---- | ---- | ------ | -------- |
| `pageSize` | int  | ❌   | 15     | 每页数量 |
| `page`     | int  | ❌   | 1      | 页码     |

**说明**: 返回当前用户所属组的所有场景快照

**错误响应**:

- `401 Unauthorized`: 未提供有效 Token

---

## 认证说明

需要认证的接口（标记 🔒）需要在请求头中携带 JWT Token：

```http
Authorization: Bearer <your_access_token>
```

**Token 过期处理**:

1. 使用 `POST /v1/auth/refresh` 接口刷新 Token
2. 使用返回的新 Token 继续请求

---

## 数据模型

### Snapshot（快照）

| 字段          | 类型   | 说明                   |
| ------------- | ------ | ---------------------- |
| `id`          | int    | 快照 ID                |
| `verse_id`    | int    | 关联场景 ID            |
| `name`        | string | 场景名称（来自 verse） |
| `description` | string | 场景描述（来自 verse） |
| `uuid`        | string | UUID                   |
| `code`        | string | 场景代码               |
| `data`        | object | 场景数据               |
| `image`       | object | 封面图片               |
| `metas`       | array  | 元数据列表             |
| `resources`   | array  | 资源列表               |
| `managers`    | array  | 管理者列表             |
| `author_id`   | int    | 作者 ID                |
| `author`      | object | 作者信息               |
| `created_at`  | string | 创建时间               |

### Tags（标签）

| 字段   | 类型   | 说明           |
| ------ | ------ | -------------- |
| `id`   | int    | 标签 ID        |
| `name` | string | 标签名称       |
| `key`  | string | 标签键（唯一） |
| `type` | string | 标签类型       |

---

## 接口汇总

| 方法     | 路由                  | 认证 | 说明         |
| -------- | --------------------- | ---- | ------------ |
| POST     | `/v1/auth/login`      | ❌   | 登录         |
| POST     | `/v1/auth/refresh`    | ❌   | 刷新 Token   |
| GET/HEAD | `/v1/server/test`     | ❌   | 测试接口     |
| GET      | `/v1/server/public`   | ❌   | 公开场景列表 |
| GET      | `/v1/server/checkin`  | ❌   | 打卡场景列表 |
| GET      | `/v1/server/snapshot` | ❌   | 单个快照     |
| GET      | `/v1/server/tags`     | ❌   | 标签列表     |
| GET      | `/v1/server/private`  | ✅   | 私有场景列表 |
| GET      | `/v1/server/group`    | ✅   | 组场景列表   |
