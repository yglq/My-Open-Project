# LQ Cloud Console

轻量级云后台管理系统。支持多数据库管理、子用户系统、私聊/群聊、工作室协作、卡密系统，带完整的对外 API。

## 功能

- 多数据库管理：每个用户可创建独立数据库，Tokey 鉴权
- 子用户系统：独立子用户表，权限分级（owner/editor/viewer）
- 私聊 / 群聊：文本、图片、红包、工作室邀请
- 工作室协作：聊天邀请加入，服务端校验
- 卡密系统：批量生成、分页列表、兑换、删除
- 对外 API：23 个接口，ApiToken 鉴权

## 技术栈

PHP 8.2 / MySQL 5.7+ / 原生 HTML/CSS/JS（无框架）/ Nginx + PHP-FPM

数据库连接统一走 `lib/db.php` 的 `GetPDO()`。

## 目录结构

```
index.html               SPA 入口（底部导航 + 子页面容器）
404.html
pages/                   所有子页面（登录、注册、后台管理、API 文档等，45 个）
js/                      前端 JS 模块（core.js 负责页面加载和登录检查）
lib/                     公共库（端点 require lib/db.php 后全部可用）
  db.php                 数据库连接 + 公共函数
  config.php             安全配置（dev_mode、encrypt、encrypt_key）
  http.php               HTTP 助手（requirePost / apiOk / postParam）
  crypto.php             XXTEA 传输加密
  password.php           密码处理（hashPwd / hashSubPwd / verifyPwd）
images/                  图标与图片资源
user/                    主用户接口（注册、登录、信息、搜索、好友）
database/                数据库/工作室接口（创建、加入、列表、Tokey 管理）
sub_user/                子用户管理接口（后台 CRUD）
chat/                    私聊接口
group/                   群聊接口
admin/                   后台配置接口
cardkey/                 卡密接口（控制台用，Tokey 鉴权）
API/                     对外公开 API（ApiToken 鉴权，第三方开发者调用）
avatars/ sub_avatars/ chat_images/ group_avatars/   上传目录（不入库）
reset_db.sql             数据库重置脚本
```

约定：

- 前端页面间跳转一律走 `pages/`（SPA 内由 `js/core.js` 的 `loadSubPage('X.html')` 加载）
- 接口请求路径一律用根相对路径，如 `/user/login.php`、`/admin/admin_config_get.php`
- `API/` 下的接口是对外契约，路径不要动；其余目录是控制台内部接口

## 数据库设计

三个 schema：`game_data`（系统核心）、`user_data`（子用户）、`card_data`（卡密）。

### game_data

- `users`：主用户（user_id, username, password[bcrypt], nickname, avatar, gender, bio, status, role, vip_time, svip_time, gold, exp, chat_list, reg_ip, login_ip）
- `data_tables_registry`：每个主用户的数据库注册表，核心字段 `table_data`（JSON：`[{table, name, tokey}, ...]`）
- `admin_config`：每个数据库的独立配置，30+ 字段（各类开关、限制、奖励、ApiToken、子用户密码哈希开关 `sub_pwd_hash`、自定义变量/JSON 定义、`studio_members`、`redeem_log` 等）
- `chat_data`：聊天数据，一行一个对话，`chat_json` 存完整 JSON（Members、Messages、ReadList）
- `server_config`：全局配置（工作室/群聊人数上限等）

### user_data

每个 `ConfigId` 对应一张物理子用户表（21 位随机表名）。固定字段：`id, avatar, username, nickname, password, gender, bio, gold, exp, title, follow, fans, ban_time, vip_time, can_change_pwd, sign_date, created_at, login_at, reg_ip`。自定义变量和 JSON 表通过 `ALTER TABLE` 动态加列。

### card_data

每个 `ConfigId` 对应 `{config_id}_cardkey` 表：`id, code, type(1金币/2会员/3变量/4JSON), coin_num, vip_days, value_list, json_list, used_at, used_by, used_username, used_ip, created_at`。

## 鉴权（三级）

| 级别 | 名称 | 验证函数 | 场景 | 存储 |
| --- | --- | --- | --- | --- |
| 1 | Password | `verifyPwd()` | 子用户操作自己的数据 | `user_data.{table}.password` |
| 2 | ApiToken | `validateApiToken()` | 第三方 API 调用 | `admin_config.api_token` |
| 3 | Tokey | `validateTokey()` / `validateTokeyGlobal()` | 后台管理操作 | `data_tables_registry.table_data` |

`API/notice/notice_update.php` 故意用 Tokey 而不是 ApiToken：ApiToken 出现在每个 API 请求里，暴露面大；公告修改属于高权限操作，用暴露面更小的 Tokey。

工作室邀请：邀请消息本身不含密钥。`database/studio_join.php` 服务端校验邀请消息存在、发送者是成员，之后才把 Tokey 写入加入者的 registry——Tokey 不经过聊天内容。

## 密码设计（lib/password.php）

```php
hashPwd($p)                // 主用户专用，始终 bcrypt
hashSubPwd($p, $useHash)   // 子用户：1=bcrypt，0=明文（由 sub_pwd_hash 控制）
verifyPwd($input, $stored) // 自动识别 $2y$ 前缀：bcrypt 校验，否则明文比对
```

`verifyPwd` 自动兼容新旧密码，打开 `sub_pwd_hash` 后旧明文密码仍然能登录。

## 安全配置（lib/config.php）

```php
$CONFIG = [
    'dev_mode'    => true,   // 开发模式：报错显示详情、忽略传输加密
    'encrypt'     => false,  // XXTEA 传输加密开关
    'encrypt_key' => '',     // 加密密钥（16 位以上）
];
```

数据库连接参数优先读环境变量，留空回退本地默认值：`LQ_DB_HOST` / `LQ_DB_PORT` / `LQ_DB_NAME` / `LQ_DB_USER` / `LQ_DB_PASS`（见 `lib/db.php`）。生产环境务必配环境变量，不要把真实密码提交进仓库。

## 端点写法约定

```php
require_once __DIR__ . '/../lib/db.php';   // 深一层目录就 ../../
header('Content-Type: application/json; charset=utf-8');

try {
    requirePost();                          // GET+POST 双支持的只读接口除外
    $ConfigId = postParam('ConfigId');      // 必填参数
    $Bio      = postParam('Bio', false);    // 可选参数
    // ...业务逻辑
    apiOk(['data' => $result]);             // {"code":0,"data":...}
} catch (Exception $e) {
    http_response_code(400);
    apiError($e);                           // dev_mode 显示详情，生产只给通用错误
}
```

## 数据重置

```bash
export MYSQL_PWD="$LQ_DB_PASS"
mysql -u root -h 127.0.0.1 -e "
  USE game_data;
  TRUNCATE TABLE users;
  TRUNCATE TABLE data_tables_registry;
  TRUNCATE TABLE admin_config;
  TRUNCATE TABLE chat_data;
" && mysql -u root -h 127.0.0.1 -N -e "
  SELECT CONCAT('DROP TABLE IF EXISTS user_data.\`', table_name, '\`;')
  FROM information_schema.tables WHERE table_schema='user_data'
" | mysql -u root -h 127.0.0.1 && mysql -u root -h 127.0.0.1 -N -e "
  SELECT CONCAT('DROP TABLE IF EXISTS card_data.\`', table_name, '\`;')
  FROM information_schema.tables WHERE table_schema='card_data'
" | mysql -u root -h 127.0.0.1 && rm -rf avatars/* sub_avatars/* chat_images/* && echo "全部清空完成"
```

## 常见问题

| 现象 | 原因 / 解决 |
| --- | --- |
| `Table doesn't exist` | 库刚清空，注册第一个账号会自动建表 |
| 注册报 `finfo_open` | `user/register.php` 用的是 `getimagesize()`，不需要 fileinfo |
| `Unknown column` | `createSubUserTable()` 已统一建表结构 |
| 保存自定义配置报表不存在 | `admin_config_save.php` 有表存在检查 |
| 卡密表 `vip_days` 报错 | 旧表残留，删掉让 `cardkey/generate.php` 重建 |

## 待做

- 卡密删除前端交互（按钮 + 调 `cardkey/delete.php`）
- 请求频率限制
- 子用户 Session Token（替代每请求传明文密码）
- 群聊 @ 功能
- WebSocket 长连接聊天
- 论坛模块 / 文档系统 / 移动端 APP

## 作者

翎乾 · [B站](https://space.bilibili.com/3493124601022890) · [lingqiannb@gmail.com](mailto:lingqiannb@gmail.com)
