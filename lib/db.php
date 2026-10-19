<?php
// 数据库配置文件
// 作用：连接 MySQL 数据库，其他文件通过 GetPDO() 使用
// 自动引入安全配置和加密/密码库

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/crypto.php';
require_once __DIR__ . '/password.php';
require_once __DIR__ . '/http.php';

// 数据库连接参数
// 优先读环境变量（生产环境务必在服务器上配置，不要把真实密码提交到仓库）：
//   LQ_DB_HOST / LQ_DB_PORT / LQ_DB_NAME / LQ_DB_USER / LQ_DB_PASS
// 留空时回退到本地开发默认值
$Host     = getenv('LQ_DB_HOST') ?: '127.0.0.1';  // 数据库服务器地址
$Port     = getenv('LQ_DB_PORT') ?: '3306';       // MySQL 端口
$DbName   = getenv('LQ_DB_NAME') ?: 'game_data';  // 数据库名
$UserName = getenv('LQ_DB_USER') ?: 'root';       // 数据库用户名
$PassWord = getenv('LQ_DB_PASS') ?: '123456';     // 数据库密码（本地开发默认值）

// 字符集
$Charset = 'utf8mb4';

try {
    // 创建 PDO 连接
    $Pdo = new PDO(
        "mysql:host=$Host;port=$Port;dbname=$DbName;charset=$Charset",
        $UserName,
        $PassWord
    );
    
    // 设置错误模式：抛出异常
    $Pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 设置默认获取模式：关联数组
    $Pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    // 连接失败：开发模式显示详情，生产模式走统一出口（不泄露数据库信息）
    apiError($e);
}

// 获取数据库连接
function GetPDO() {
    global $Pdo;
    return $Pdo;
}

// 生成随机字符串（公共函数）
// $_length: 字符串长度
// $_type: 'avatar' 仅大写字母+数字，默认全部字母+数字
function randomString($_length, $_type = 'all') {
    if ($_type === 'avatar') {
        $_chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ123456789';
    } elseif ($_type === 'table') {
        // 仅小写字母+数字，避免 ConfigId/表名大小写不一致（Linux 下 MySQL 表名大小写敏感）
        $_chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    } else {
        $_chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    }
    $_result = '';
    $_max = strlen($_chars) - 1;
    for ($_i = 0; $_i < $_length; $_i++) {
        $_result .= $_chars[random_int(0, $_max)];
    }
    return $_result;
}

// ========== 权限验证（公共函数） ==========

// 验证 ApiToken：用于对外 API 接口（级别 2）
// ConfigId: 数据库标识
// ApiToken: 调用方传入的 token
// 说明：当 api_token_switch=1 时验证，否则跳过
function validateApiToken($ConfigId, $ApiToken) {
    if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', (string)$ConfigId)) throw new Exception('ConfigId 非法');
    $_pdo = GetPDO();
    $_stmt = $_pdo->prepare("SELECT api_token, api_token_switch FROM admin_config WHERE config_id = :cid LIMIT 1");
    $_stmt->execute(['cid' => $ConfigId]);
    $_config = $_stmt->fetch();
    if (!$_config) throw new Exception('配置不存在');
    if (intval($_config['api_token_switch'] ?? 0) === 1) {
        if ($_config['api_token'] !== $ApiToken) throw new Exception('ApiToken 错误');
    }
}

// 验证 Tokey：用于后台管理接口（级别 3）
// UserId: 主用户 ID
// ConfigId: 数据库标识
// Tokey: 数据库密钥
function validateTokey($UserId, $ConfigId, $Tokey) {
    if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', (string)$ConfigId)) throw new Exception('ConfigId 非法');
    $_pdo = GetPDO();
    $_stmt = $_pdo->prepare("SELECT table_data FROM data_tables_registry WHERE user_id = :uid");
    $_stmt->execute(['uid' => $UserId]);
    $_data = json_decode($_stmt->fetchColumn(), true) ?: [];
    foreach ($_data as $_item) {
        if (($_item['table'] ?? '') === $ConfigId && ($_item['tokey'] ?? '') === $Tokey) {
            return;
        }
    }
    throw new Exception('Tokey 验证失败');
}

// 签发主用户会话令牌（登录时调用），返回明文 token 并写库
function issueSessionToken($UserId) {
    $_token = bin2hex(random_bytes(24)); // 48 字符
    $_pdo = GetPDO();
    $_stmt = $_pdo->prepare("UPDATE users SET session_token = :t WHERE user_id = :uid");
    $_stmt->execute(['t' => $_token, 'uid' => $UserId]);
    return $_token;
}

// 校验主用户会话令牌
function validateSessionToken($UserId, $SessionToken) {
    if ($SessionToken === '') return false;
    $_pdo = GetPDO();
    $_stmt = $_pdo->prepare("SELECT session_token FROM users WHERE user_id = :uid LIMIT 1");
    $_stmt->execute(['uid' => $UserId]);
    $_stored = $_stmt->fetchColumn();
    return $_stored !== false && $_stored !== null && $_stored !== ''
        && hash_equals($_stored, $SessionToken);
}

// 控制台接口统一鉴权：UserId + SessionToken（兼容回退 Password）
// 从 $_POST 取参；校验通过返回 user_id，否则抛异常
function authUser() {
    $_userId = intval($_POST['UserId'] ?? 0);
    if ($_userId <= 0) throw new Exception('参数错误：UserId 必填');

    $_pdo = GetPDO();
    $_stmt = $_pdo->prepare("SELECT password, session_token, status FROM users WHERE user_id = :uid LIMIT 1");
    $_stmt->execute(['uid' => $_userId]);
    $_user = $_stmt->fetch();
    if (!$_user) throw new Exception('用户不存在');
    if ((int)$_user['status'] === 1) throw new Exception('账号已被封禁');

    // 优先会话令牌；无令牌时兼容旧密码校验
    $_token = $_POST['SessionToken'] ?? '';
    if ($_token !== '') {
        if (hash_equals((string)$_user['session_token'], $_token)) return $_userId;
        throw new Exception('登录状态已失效，请重新登录');
    }
    $_pwd = $_POST['Password'] ?? '';
    if ($_pwd !== '' && verifyPwd($_pwd, $_user['password'])) return $_userId;

    throw new Exception('未登录或凭证无效');
}

// 简单限流：同一 IP+端点 在窗口期内超过次数则拒绝
// 用法：rateLimit('user/login', 5, 60) → 每分钟最多 5 次
function rateLimit($endpoint, $maxCount = 10, $windowSec = 60) {
    $_pdo = GetPDO();
    // 表按需自建，无迁移成本
    $_pdo->exec("CREATE TABLE IF NOT EXISTS rate_limit (
        `endpoint` VARCHAR(64) NOT NULL,
        `ip` VARCHAR(45) NOT NULL,
        `window_start` INT UNSIGNED NOT NULL,
        `count` INT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (endpoint, ip)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $_ip = getClientIp();
    $_now = time();
    $_win = $_now - ($_now % $windowSec);

    $_pdo->beginTransaction();
    try {
        $_stmt = $_pdo->prepare("SELECT window_start, count FROM rate_limit WHERE endpoint = :ep AND ip = :ip FOR UPDATE");
        $_stmt->execute(['ep' => $endpoint, 'ip' => $_ip]);
        $_row = $_stmt->fetch();

        if (!$_row || (int)$_row['window_start'] !== $_win) {
            $_stmt2 = $_pdo->prepare("REPLACE INTO rate_limit (endpoint, ip, window_start, count) VALUES (:ep, :ip, :w, 1)");
            $_stmt2->execute(['ep' => $endpoint, 'ip' => $_ip, 'w' => $_win]);
            $_pdo->commit();
            return;
        }
        if ((int)$_row['count'] >= $maxCount) {
            $_pdo->commit();
            throw new Exception('请求过于频繁，请稍后再试');
        }
        $_stmt3 = $_pdo->prepare("UPDATE rate_limit SET count = count + 1 WHERE endpoint = :ep AND ip = :ip");
        $_stmt3->execute(['ep' => $endpoint, 'ip' => $_ip]);
        $_pdo->commit();
    } catch (Exception $e) {
        if ($_pdo->inTransaction()) $_pdo->rollBack();
        throw $e;
    }
}

// ========== 公共工具函数 ==========

// 创建卡密表（按 ConfigId 隔离，每个数据库一张独立表）
function createCardKeyTable($configId) {
    $_pdo = GetPDO();
    $_tableName = $configId . '_cardkey';
    $_sql = "CREATE TABLE IF NOT EXISTS `card_data`.`{$_tableName}` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `code` VARCHAR(50) NOT NULL UNIQUE COMMENT '卡密',
        `type` TINYINT NOT NULL DEFAULT 1 COMMENT '1金币 2会员 3自定义变量 4自定义JSON',
        `coin_num` INT DEFAULT 0 COMMENT '金币数量(type=1)',
        `vip_days` INT DEFAULT 0 COMMENT 'VIP天数(type=2)',
        `value_list` JSON DEFAULT NULL COMMENT '自定义变量奖励(type=3)',
        `json_list` JSON DEFAULT NULL COMMENT '自定义JSON奖励(type=4)',
        `used_at` DATETIME DEFAULT NULL COMMENT '兑换时间(0为未使用)',
        `used_by` INT UNSIGNED DEFAULT NULL COMMENT '兑换子用户ID',
        `used_username` VARCHAR(50) DEFAULT NULL COMMENT '兑换用户名',
        `used_ip` VARCHAR(45) DEFAULT NULL COMMENT '兑换IP',
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_code (code),
        INDEX idx_used (used_by)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $_pdo->exec($_sql);
}

// 创建子用户表（统一建表结构）
function createSubUserTable($tableName) {
    $_pdo = GetPDO();
    $_createSQL = "CREATE TABLE IF NOT EXISTS `user_data`.`{$tableName}` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `avatar` VARCHAR(50) DEFAULT '',
        `username` VARCHAR(50) NOT NULL,
        `nickname` VARCHAR(50) DEFAULT '',
        `password` VARCHAR(255) NOT NULL,
        `gender` TINYINT DEFAULT 0,
        `bio` TEXT,
        `gold` INT DEFAULT 0,
        `exp` INT DEFAULT 0,
        `title` VARCHAR(50) DEFAULT '',
        `follow` JSON DEFAULT NULL,
        `fans` JSON DEFAULT NULL,
        `ban_time` DATETIME DEFAULT NULL,
        `vip_time` DATETIME DEFAULT NULL,
        `can_change_pwd` TINYINT DEFAULT 1,
        `sign_date` DATE DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `login_at` DATETIME DEFAULT NULL,
        `reg_ip` VARCHAR(45) DEFAULT '',
        INDEX idx_username (username)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $_pdo->exec($_createSQL);
}

// 确保子用户表中存在自定义变量列与自定义 JSON 列（不存在则自动创建）
// $customVarsJson:        admin_config.custom_vars        -> [{"name":"value1","type":"value"}, ...]
// $customVarsDefJson:     admin_config.custom_vars_defaults -> {"value1":0, ...}
// $customTblDefJson:      admin_config.custom_table_defaults -> {"tblName":{"col":default}, ...}
function ensureCustomColumns($ConfigId, $customVarsJson, $customVarsDefJson, $customTblDefJson) {
    $_pdo = GetPDO();
    $_safeTbl = preg_replace('/[^A-Za-z0-9_]/', '', (string)$ConfigId);
    $_tbl = "`user_data`.`{$_safeTbl}`";

    $_names = [];
    // 来自 custom_vars 定义
    $_vars = json_decode($customVarsJson ?: '[]', true) ?: [];
    foreach ($_vars as $_v) {
        if (is_array($_v)) $_names[] = preg_replace('/[^A-Za-z0-9_]/', '', (string)($_v['name'] ?? ''));
    }
    // 来自 custom_vars_defaults 的 key
    $_def = json_decode($customVarsDefJson ?: '[]', true) ?: [];
    foreach ($_def as $_k => $_v) $_names[] = preg_replace('/[^A-Za-z0-9_]/', '', (string)$_k);
    $_names = array_unique(array_filter($_names));

    foreach ($_names as $_n) {
        $_chk = $_pdo->query("SHOW COLUMNS FROM {$_tbl} LIKE '{$_n}'");
        if ($_chk->rowCount() === 0) {
            $_pdo->exec("ALTER TABLE {$_tbl} ADD COLUMN `{$_n}` VARCHAR(255) DEFAULT '0'");
        }
    }

    // 自定义 JSON 表列
    $_tbls = json_decode($customTblDefJson ?: '[]', true) ?: [];
    foreach ($_tbls as $_tname => $_cols) {
        $_tname = preg_replace('/[^A-Za-z0-9_]/', '', (string)$_tname);
        if ($_tname === '') continue;
        $_chk = $_pdo->query("SHOW COLUMNS FROM {$_tbl} LIKE '{$_tname}'");
        if ($_chk->rowCount() === 0) {
            $_pdo->exec("ALTER TABLE {$_tbl} ADD COLUMN `{$_tname}` JSON DEFAULT NULL");
        }
    }
}

// 获取客户端 IP
function getClientIp() {
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $_ipList = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($_ipList[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

// 验证上传图片
// $maxMb: 最大 MB 数，默认 2MB
// $requirePng: 是否必须 PNG 格式，默认 false
function validateImage($file, $maxMb = 2, $requirePng = false) {
    if (empty($file) || $file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('请选择图片');
    }
    $_imgInfo = @getimagesize($file['tmp_name']);
    if ($_imgInfo === false) {
        throw new Exception('不是有效的图片');
    }
    if ($file['size'] > $maxMb * 1024 * 1024) {
        throw new Exception("图片不能超过 {$maxMb}MB");
    }
    if ($requirePng && $_imgInfo['mime'] !== 'image/png') {
        throw new Exception('头像必须是 PNG 格式');
    }
}

// 生成唯一表名
function generateUniqueTableName() {
    $_pdo = GetPDO();
    $_maxRetry = 20;
    for ($_i = 0; $_i < $_maxRetry; $_i++) {
        $_tableName = randomString(21, 'table');
        $_stmt = $_pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'user_data' AND table_name = :tname");
        $_stmt->execute(['tname' => $_tableName]);
        if ($_stmt->fetchColumn() == 0) {
            return $_tableName;
        }
    }
    throw new Exception('表名生成失败，请重试');
}

// 仅允许字母数字下划线作为 SQL 标识符（表名/列名），防止注入
function _safeIdent($name) {
    return preg_replace('/[^A-Za-z0-9_]/', '', (string)$name);
}

// 解析自定义变量名（兼容新旧格式）
// 新格式: [{"name":"score","type":"value"},...]
// 旧格式: ["score","power",...]
function parseCustomVarNames($jsonStr) {
    $raw = json_decode($jsonStr ?: '[]', true) ?: [];
    $names = [];
    foreach ($raw as $v) {
        $names[] = is_array($v) ? ($v['name'] ?? '') : $v;
    }
    return $names;
}

// 解析自定义表定义（兼容新旧格式）
// 新格式: {"backpack":[{"name":"item_id","type":"value"},...],...}
// 旧格式: {"backpack":["item_id","count"],...}
function parseCustomTableDefs($jsonStr) {
    $raw = json_decode($jsonStr ?: '{}', true) ?: [];
    $defs = [];
    foreach ($raw as $tblName => $cols) {
        $defs[$tblName] = [];
        foreach ((array)$cols as $c) {
            $defs[$tblName][] = is_array($c) ? ($c['name'] ?? '') : $c;
        }
    }
    return $defs;
}

// 返回 JSON 错误响应
function jsonError($message, $httpCode = 400) {
    http_response_code($httpCode);
    echo json_encode(['code' => 1, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

// 是否开发模式（来自 config.php 的 $CONFIG['dev_mode']）
function isDevMode() {
    global $CONFIG;
    return !empty($CONFIG['dev_mode']);
}

// 统一错误出口：开发模式原样返回异常信息；生产模式只返回通用提示并写服务器日志
function apiError($e) {
    if (isDevMode()) {
        jsonError($e->getMessage());
    }
    error_log('[LQ] ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    jsonError('系统繁忙，请稍后重试');
}

// 生产模式：不向客户端暴露任何错误细节（含 SQL/表结构），仅记录到服务器日志；
// 同时兜底未捕获的异常，避免 PHP 致命错误直接输出到响应
if (!isDevMode()) {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL);
    ini_set('log_errors', '1');
    set_exception_handler(function ($e) { apiError($e); });
}

// 验证 Tokey（全局版）：用于无需 UserId 的场景，如 notice_update API
function validateTokeyGlobal($ConfigId, $Tokey) {
    if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', (string)$ConfigId)) throw new Exception('ConfigId 非法');
    $_pdo = GetPDO();
    // JSON_SEARCH 只取包含该 ConfigId 的行，不再全表解码
    $_stmt = $_pdo->prepare("SELECT table_data FROM data_tables_registry WHERE JSON_SEARCH(table_data, 'one', :cid) IS NOT NULL");
    $_stmt->execute(['cid' => $ConfigId]);
    $_all = $_stmt->fetchAll();
    foreach ($_all as $_row) {
        $_data = json_decode($_row['table_data'], true) ?: [];
        foreach ($_data as $_item) {
            if (($_item['table'] ?? '') === $ConfigId && ($_item['tokey'] ?? '') === $Tokey) {
                return;
            }
        }
    }
    throw new Exception('Tokey 验证失败');
}
