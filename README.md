# LQ Cloud Console

> 轻量级云后台管理系统 —— 为个人开发者及小团队打造。支持多数据库管理、子用户系统、实时消息、工作室协作、卡密系统，具备完整的后端 API 和前端文档。

---

## ✨ 功能特性

- 🗄 **多数据库管理**：每个用户可创建独立数据库，支持 Tokey 鉴权
- 👥 **子用户系统**：独立子用户表、权限分级（owner/editor/viewer）
- 💬 **实时消息**：私聊 / 群聊，支持文本、图片、红包、工作室邀请
- 🏢 **工作室协作**：通过聊天邀请加入，服务端校验，零密钥泄露
- 🔑 **卡密系统**：批量生成、分页列表、兑换、删除
- 🔌 **完整 API**：19+ 对外接口，支持 ApiToken 鉴权

---

## 🛠 技术栈

| 属性 | 值 |
| --- | --- |
| 后端语言 | PHP 8.2 |
| 数据库 | MySQL 5.7+ |
| 前端 | 原生 HTML / CSS / JavaScript（无框架） |
| 架构 | SPA 动态加载 + 前端缓存 |
| Web 服务器 | Nginx + PHP-FPM |
| 数据库连接 | PDO（`db.php` 中 `GetPDO()` 全局函数） |
| 移动端 | Kotlin / Android（`kotlin_test/`，项目名 `CardKeyTest`） |

---

## 👤 作者

<p align="center">
  <img src="images/icon.jpg" width="120" height="120" alt="翎乾" style="border-radius:50%;">
</p>

<p align="center">
  <b>翎乾</b> · 15 岁全栈独立开发者
</p>

| 项目 | 信息 |
| --- | --- |
| 🅱️ B站 | [@翎乾](https://space.bilibili.com/3493124601022890) |
| ✉️ 邮箱 | [lingqiannb@gmail.com](mailto:lingqiannb@gmail.com) |

---

## 📂 项目目录结构

```
LQ-Cloud-Console/                  # 项目根目录
│
├── config.php                     # 【核心】安全配置（dev_mode、encrypt、encrypt_key）
├── db.php                         # 【核心】数据库连接 + 所有公共函数（15+ 个）
├── index.html                     # 【核心】主框架（SPA 入口）
│
├── lib/                           # 公共库
│   ├── crypto.php                 # XXTEA 加密/解密（encrypt / decrypt）
│   └── password.php               # 密码处理（hashPwd / hashSubPwd / verifyPwd）
│
├── js/                            # 前端 JS 模块（12 个）
│   ├── core.js                    # 登录检查、页面加载器、Tab 切换、Banner 轮播
│   ├── user.js                    # 用户信息、昵称/密码修改、退出登录
│   ├── config.js                  # 后台配置加载/保存（含 sub_pwd_hash 开关）
│   ├── chat.js                    # 消息列表、聊天详情、发送消息、工作室邀请
│   ├── chat_group.js              # 群聊创建/管理、数据库操作
│   ├── admin_db.js                # 全局数据库选择器
│   ├── admin_notice.js            # 公告管理
│   ├── admin_users.js             # 子用户 CRUD + 创建/编辑弹窗
│   ├── admin_custom.js            # 自定义变量/JSON 管理
│   ├── admin_cardkey.js           # 卡密系统（加载/生成/筛选）
│   ├── ranking.js                 # 自定义变量/JSON 排行榜
│   ├── debug.js                   # 调试面板（日志/请求/错误/变量）
│   └── init.js                    # 初始化加载
│
├── user/           (8 文件)       # 主用户系统
│   ├── register.php               # 注册（自动创建子用户表 + admin_config）
│   ├── login.php                  # 登录
│   ├── check_login.php            # 登录状态验证
│   ├── user_info.php              # 获取主用户信息
│   ├── user_search.php            # 用户搜索
│   ├── friend_list.php            # 好友列表
│   ├── update_nickname.php        # 修改昵称
│   └── update_password.php        # 修改密码（始终 bcrypt 哈希）
│
├── database/       (8 文件)       # 数据库管理
│   ├── database_create.php        # 创建数据库（自动建子用户表 + admin_config）
│   ├── database_join.php          # 加入他人数据库
│   ├── database_list.php          # 数据库列表
│   ├── database_remove.php        # 移除数据库
│   ├── database_update_tokey.php  # 修改 Tokey
│   ├── database_remove_member.php # 移除协作成员
│   ├── user_databases.php         # 用户工作室列表
│   └── studio_join.php            # 加入工作室（服务端校验邀请消息）
│
├── sub_user/       (6 文件)       # 子用户管理（后台）
│   ├── sub_users_list.php         # 列表（分页）
│   ├── sub_user_create.php        # 创建（接收 HashPwd 参数）
│   ├── sub_user_detail.php        # 详情
│   ├── sub_user_update.php        # 更新（密码特殊处理 HashPwd）
│   ├── sub_user_delete.php        # 删除
│   └── sub_user_avatar.php        # 上传头像
│
├── chat/           (4 文件)       # 私聊
│   ├── chat_create.php            # 创建私聊
│   ├── chat_send.php              # 发送消息（文本/图片/红包/索要/邀请工作室）
│   ├── chat_list.php              # 消息列表
│   └── chat_detail.php            # 聊天详情（分页）
│
├── group/          (6 文件)       # 群聊
│   ├── group_create.php           # 创建群聊
│   ├── group_members.php          # 群成员列表
│   ├── group_invite.php           # 邀请进群
│   ├── group_kick.php             # 踢出群聊
│   ├── group_clear.php            # 清空聊天记录
│   └── group_leave.php            # 退出/解散群聊
│
├── admin/          (7 文件)       # 后台管理
│   ├── admin_config_get.php       # 获取配置
│   ├── admin_config_save.php      # 保存配置（含 sub_pwd_hash + 子用户表同步）
│   ├── admin_databases.php        # 获取用户所有数据库
│   ├── notice_save.php            # 保存公告
│   ├── coin_accept.php            # 领取金币红包
│   ├── server_config_get.php      # 获取服务器配置
│   └── api_ping.php               # API 测速
│
├── cardkey/        (3 文件)       # 卡密后端
│   ├── generate.php               # 批量生成卡密
│   ├── list.php                   # 分页列表
│   └── delete.php                 # 删除未使用的卡密
│
├── API/                           # 对外 API
│   ├── notice/
│   │   ├── notice_get.php         # [GET/POST] 获取公告（ApiToken）
│   │   └── notice_update.php      # [GET/POST] 修改公告（TokeyGlobal）
│   └── user/       (17 文件)      # 子用户 API
│       ├── register.php           # 注册（根据 sub_pwd_hash 决定密码存储方式）
│       ├── login.php              # 登录
│       ├── sign.php               # 签到
│       ├── get_var.php            # 获取指定变量
│       ├── get_all.php            # 获取全部公开数据
│       ├── update_nickname.php    # 修改昵称
│       ├── update_username.php     # 修改用户名
│       ├── update_avatar.php      # 上传头像
│       ├── update_password.php     # 修改密码（根据 sub_pwd_hash）
│       ├── update_bio.php         # 修改简介
│       ├── update_custom.php      # 修改自定义变量
│       ├── update_custom_table.php # 修改自定义 JSON 表
│       ├── follow.php             # 关注
│       ├── unfollow.php           # 取消关注
│       ├── get_follow.php         # 获取关注列表
│       ├── get_fans.php           # 获取粉丝列表
│       └── admin_update.php       # 管理员修改积分/会员/称号
│
├── Home_Admin_*.html              # 后台管理子页面（10+ 个）
├── Main_*.html                    # 主标签页（4 个）
├── My_*.html                      # 个人中心子页面（2 个）
├── Login.html / Register.html     # 登录/注册页面
├── reset_db.sql                   # 数据库一键重置脚本
└── *.png                          # 图标资源
```

---

## 🗄 数据库设计

### 3.1 `game_data`（系统核心库）

**`users` — 主用户表**

| 字段 | 类型 | 说明 |
| --- | --- | --- |
| user_id | INT PK | 用户 ID |
| username | VARCHAR(50) UNIQUE | 用户名 |
| password | VARCHAR(255) | 密码（始终 bcrypt 哈希） |
| nickname | VARCHAR(50) | 昵称 |
| avatar | VARCHAR(50) | 头像文件名 |
| gender | TINYINT | 0未知/1男/2女 |
| bio | TEXT | 个人简介 |
| status | TINYINT | 0正常/1封号 |
| role | VARCHAR(20) | owner/editor/viewer |
| vip_time | DATETIME | VIP 到期时间 |
| svip_time | DATETIME | SVIP 到期时间 |
| gold | INT | 金币 |
| exp | INT | 经验值 |
| chat_list | JSON | 消息列表 |
| reg_ip / login_ip | VARCHAR(45) | 注册/登录 IP |

**`data_tables_registry` — 数据库注册表**

| 字段 | 类型 | 说明 |
| --- | --- | --- |
| id | INT PK | |
| user_id | INT | 所属用户 |
| table_data | JSON | 所有数据库配置 `[{"table":"xxx","name":"xxx","tokey":"xxx"},...]` |
| record | JSON | 操作记录 |
| member_count | INT | 协作成员数 |

**`admin_config` — 每个数据库的独立配置**

包含 30+ 字段：登录/注册/改名/换头像等开关、长度限制、IP 限制、注册/签到奖励、卡密配置、公告配置、服务器状态、API Token、`sub_pwd_hash`（子用户密码哈希开关）、自定义变量/JSON 定义及默认值、`studio_members`、`redeem_log`（兑换记录）等。

**`chat_data` — 聊天数据**

| 字段 | 类型 | 说明 |
| --- | --- | --- |
| id | INT PK | |
| chat_id | VARCHAR(21) UNIQUE | 对话 ID |
| chat_json | JSON | 完整对话数据（Members、Messages、ReadList 等） |

**`server_config` — 全局服务器配置**

| 字段 | 类型 | 说明 |
| --- | --- | --- |
| studio_limit | JSON | 工作室人数上限（按等级） |
| group_color | JSON | 群聊颜色等级 |
| group_limit | JSON | 群聊人数上限 |

### 3.2 `user_data`（子用户数据库）

每个 `ConfigId` 对应一张独立的子用户表（21 位随机表名）。固定字段：`id, avatar, username, nickname, password, gender, bio, gold, exp, title, follow, fans, ban_time, vip_time, can_change_pwd, sign_date, created_at, login_at, reg_ip`。支持通过 `ALTER TABLE` 动态添加自定义变量列和 JSON 表列。

### 3.3 `card_data`（卡密数据库）

每个 `ConfigId` 对应一张独立卡密表 `{config_id}_cardkey`：

| 字段 | 类型 | 说明 |
| --- | --- | --- |
| id | INT PK | |
| code | VARCHAR(50) UNIQUE | 卡密 |
| type | TINYINT | 1金币/2会员/3自定义变量/4自定义JSON |
| coin_num | INT | 金币数量(type=1) |
| vip_days | INT | VIP天数(type=2) |
| value_list | JSON | 变量奖励(type=3) |
| json_list | JSON | JSON奖励(type=4) |
| used_at | DATETIME | 兑换时间(NULL=未使用) |
| used_by | INT | 兑换子用户ID |
| used_username | VARCHAR(50) | 兑换用户名 |
| used_ip | VARCHAR(45) | 兑换IP |
| created_at | DATETIME | 创建时间 |

---

## 🔐 Token 权限体系（三级）

| 级别 | 名称 | 验证函数 | 使用场景 | 存储位置 |
| --- | --- | --- | --- | --- |
| 1 | Password | `verifyPwd()` | 子用户操作自己的数据 | `user_data.{table}.password` |
| 2 | ApiToken | `validateApiToken($ConfigId, $ApiToken)` | 第三方开发者 API 调用 | `admin_config.api_token` |
| 3 | Tokey | `validateTokey($UserId, $ConfigId, $Tokey)` | 后台管理员操作 | `data_tables_registry.table_data` JSON 中 |

> 设计决策：API `notice/notice_update.php`（修改公告）使用 Tokey 而非 ApiToken。理由：ApiToken 暴露面大（17 个 API 每请求都传），Tokey 暴露面小，防止 ApiToken 泄露后公告被篡改。

### 工作室邀请安全机制

加入工作室**不依赖聊天消息中的任何密钥**。流程：

1. 成员在聊天中发送 `InviteStudio` 消息（仅含 `ConfigId` / `StudioName`，无 Tokey）；
2. 接收方点击「加入」时，前端上报 `ChatId` + `MsgId`；
3. 服务端 `studio_join.php` 校验：
   - 该 `MsgId` 消息存在且类型为 `InviteStudio`、`ConfigId` 匹配；
   - 当前用户在该对话的 `Members` 中；
   - 邀请发送者确为该工作室的成员/创建者。
4. 全部通过才允许加入，Tokey 仅在服务端内部读取，**绝不出服务端**。

---

## 🔑 密码系统设计

### 5.1 核心函数（`lib/password.php`）

```php
hashPwd($password)                              // 始终 bcrypt（主用户专用）
hashSubPwd($password, $useHash)                 // $useHash=1→bcrypt, 0→明文（子用户）
verifyPwd($input, $stored)                      // 自动检测 $2y$ 开头→bcrypt，否则明文
```

### 5.2 密码存储规则

| 用户类型 | 存储方式 | 控制方式 |
| --- | --- | --- |
| 主用户（users 表） | 始终 bcrypt | 无开关，强制哈希 |
| 子用户（user_data 表） | 由 `admin_config.sub_pwd_hash` 控制 | 0=明文, 1=bcrypt |

### 5.3 兼容性

`verifyPwd()` 自动检测 bcrypt（`$2y$` 开头）还是明文——零迁移成本。开关从 0→1 后旧明文密码仍能验证，新密码自动存为哈希。

---

## ⚙️ 安全配置（`config.php`）

```php
$CONFIG = [
    'dev_mode'      => true,    // true=开发模式（优先级最高，忽略所有加密，密码明文处理）
    'encrypt'       => false,   // 是否启用 XXTEA 传输加密
    'encrypt_key'   => '',      // 加密密钥（16位以上）
];
```

> 重要：`lib/password.php` 中 `verifyPwd()` 不受 `config.php` 控制——始终自动检测 bcrypt/明文。`hashPwd()` 始终返回 bcrypt。

---

## 📚 公共函数库（`db.php`）

| 函数 | 用途 |
| --- | --- |
| `GetPDO()` | 获取全局 PDO 连接 |
| `randomString($len, $type)` | 生成随机字符串 |
| `getClientIp()` | 获取客户端 IP |
| `validateImage($file, $maxMb, $png)` | 验证上传图片 |
| `generateUniqueTableName()` | 生成 21 位唯一子用户表名 |
| `createSubUserTable($name)` | 创建子用户表（统一结构） |
| `createCardKeyTable($configId)` | 创建卡密表（在 card_data 库） |
| `validateApiToken($c, $t)` | 验证 API Token |
| `validateTokey($u, $c, $t)` | 验证数据库密钥 |
| `validateTokeyGlobal($c, $t)` | 全局 Tokey 验证（无需 UserId） |
| `parseCustomVarNames($json)` | 解析自定义变量名（兼容新旧格式） |
| `parseCustomTableDefs($json)` | 解析自定义表定义（兼容新旧格式） |
| `jsonError($msg, $code)` | 返回 JSON 错误响应 |

---

## 🧩 自定义变量 / JSON 数据结构

旧格式（纯字符串）：

```json
{ "custom_vars": ["score", "power"], "custom_tables": {"backpack": ["item_id"]} }
```

新格式（带类型，当前使用）：

```json
{
  "custom_vars": [{"name":"score","type":"value"}, {"name":"title","type":"string"}],
  "custom_tables": {"backpack": [{"name":"item_id","type":"value"}, {"name":"desc","type":"string"}]}
}
```

- `value`：数值类型，兑换时累加（`+= N`）
- `string`：文本类型，兑换时替换（`= 文本`）

`parseCustomVarNames()` / `parseCustomTableDefs()` 自动兼容两种格式。

---

## 🚀 数据重置命令

```bash
# 一键清空所有数据（密码通过环境变量传入，不要写死在命令行里）
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

---

## ❓ 常见问题排查

| 现象 | 原因 / 解决 |
| --- | --- |
| 页面报错 `Table doesn't exist` | 数据库刚清空，注册第一个账号会自动建表 |
| 注册报 `finfo_open` 错误 | `user/register.php` 已改用 `getimagesize()` |
| 子用户列表报 `Unknown column` | `createSubUserTable()` 统一了建表结构 |
| 保存自定义配置报子用户表不存在 | `admin_config_save.php` 已加表存在检查 |
| 卡密表 `vip_days` 报错 | 旧表残留，删除后重建（`cardkey/generate.php` 自动建新表） |
| 密码处理 | 主用户始终 bcrypt，子用户由 `sub_pwd_hash` 控制 |
| API 接口报错 | 全部 19 个接口已通过 PHP 语法检查 |

---

## 🔄 开发 / 生产切换

| 操作 | 文件 |
| --- | --- |
| 开发模式 | `config.php` → `dev_mode=true`（密码明文方便调试） |
| 生产模式 | `config.php` → `dev_mode=false` + `encrypt=true` + 设置密钥 |

---

## 📌 待完成功能

- [ ] 卡密删除前端交互（按钮 + 调用 `cardkey/delete.php`）
- [ ] 请求频率限制
- [ ] 子用户 Session Token（替代每请求传明文密码）
- [ ] 群聊 @ 功能
- [ ] WebSocket 长连接聊天
- [ ] 论坛模块
- [ ] 文档系统
- [ ] 移动端 APP

---

<p align="center">
  Made with ❤️ by <b>翎乾</b> ·
  <a href="https://space.bilibili.com/3493124601022890">B站</a> ·
  <a href="mailto:lingqiannb@gmail.com">Email</a>
</p>
