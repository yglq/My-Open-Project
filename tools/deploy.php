<?php
/**
 * deploy.php — 远程文件部署端点（OpenCode 式）
 *
 * 用途：让远程工具（如 Devin / 本地脚本）通过 POST 直接把文件写入服务器项目目录。
 * 把这个文件放到项目根目录（或任意 web 可访问位置），并创建令牌文件即可使用。
 *
 * 鉴权：请求必须带 Token，与令牌文件内容一致才放行。
 *   令牌来源（按优先级）：
 *   1. 环境变量 LQ_DEPLOY_TOKEN
 *   2. 同目录下 deploy.token 文件（内容为令牌，该文件不要提交 git）
 *
 * 用法（POST）：
 *   action=write  参数: Token, Path（相对项目根）, Content（原文）或 ContentBase64
 *   action=read   参数: Token, Path
 *   action=list   参数: Token, Path（目录，留空=根目录）
 *   action=delete 参数: Token, Path
 *   action=info   参数: Token
 *   action=sql    参数: Token, Db（仅限 game_data/user_data/card_data）, Sql（单条语句）
 *                 DB 密码来源：LQ_DB_PASS 环境变量 或 tools/deploy.dbpass 文件
 *
 * 示例：
 *   curl -X POST https://你的域名/tools/deploy.php \
 *     -d "action=write" -d "Token=xxx" \
 *     -d "Path=pages/Login.html" --data-urlencode "Content@Login.html"
 */

header('Content-Type: application/json; charset=utf-8');

// ========== 配置 ==========
// 项目根目录：deploy.php 所在目录的上一级（tools/ 下）。若把本文件放根目录请改回 __DIR__
$ROOT = realpath(__DIR__ . '/..');
// 禁止读写的路径（相对根目录），防自删/防覆盖令牌
$DENY = ['tools/deploy.token', '.git', '.deploy_token'];

// action=sql 允许操作的库（白名单）
$DB_WHITELIST = ['game_data', 'user_data', 'card_data'];

// MySQL 连接（action=sql 用，密码走 loadDbPass）
$DB_HOST = getenv('LQ_DB_HOST') ?: '127.0.0.1';
$DB_PORT = getenv('LQ_DB_PORT') ?: '3306';
$DB_USER = getenv('LQ_DB_USER') ?: 'root';

// ========== 工具函数 ==========
function out($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
function err($msg) {
    http_response_code(400);
    out(['code' => 1, 'message' => $msg]);
}

function loadToken() {
    $t = getenv('LQ_DEPLOY_TOKEN');
    if ($t) return $t;
    $f = __DIR__ . '/deploy.token';
    return is_file($f) ? trim(file_get_contents($f)) : '';
}

// DB 密码：环境变量优先，其次 tools/deploy.dbpass（不进 git）
function loadDbPass() {
    $p = getenv('LQ_DB_PASS');
    if ($p !== false && $p !== '') return $p;
    $f = __DIR__ . '/deploy.dbpass';
    return is_file($f) ? trim(file_get_contents($f)) : '';
}

function sqlGuard(string $db, string $sql, array $whitelist) {
    if (!in_array($db, $whitelist, true)) return '库名不允许，只能操作: ' . implode('/', $whitelist);
    $s = trim($sql);
    if ($s === '') return '缺少 Sql';
    // 语句白名单（首个关键字）
    if (!preg_match('/^(select|insert|update|delete|replace|alter|create|drop|truncate|rename|show|describe|desc)\b/i', $s)) {
        return '不支持的语句类型';
    }
    // 危险用法拦截：写文件/读文件/提权/进程操作
    if (preg_match('/(into\s+(out|dump)file|load_file|grant\b|revoke\b|shutdown|processlist|information_schema|performance_schema|\bmysql\.|\bsys\.|set\s+password)/i', $s)) {
        return '语句包含被禁止的内容';
    }
    // 防止跨库访问另外两个白名单库之外的 schema：限制语句里出现的库名只能是本库
    if (preg_match_all('/`?([a-zA-Z_][a-zA-Z0-9_]*)`?\s*\./', $s, $m)) {
        foreach ($m[1] as $schema) {
            if (!in_array(strtolower($schema), array_map('strtolower', $whitelist), true) && strtolower($schema) !== strtolower($db)) {
                return '语句引用了未授权的库: ' . $schema;
            }
        }
    }
    return null;
}

// 把相对路径解析成根目录内的绝对路径；越界返回 false
function resolvePath($root, $rel) {
    $rel = str_replace('\\', '/', trim($rel));
    if ($rel === '' || strpos($rel, '..') !== false || $rel[0] === '/' || preg_match('#^[a-zA-Z]:#', $rel)) {
        return false;
    }
    return $root . '/' . ltrim($rel, '/');
}

// ========== 主逻辑 ==========
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') err('仅支持 POST 请求');

    $Token  = trim($_POST['Token'] ?? '');
    $Action = trim($_POST['action'] ?? '');
    $Path   = $_POST['Path'] ?? '';

    $expect = loadToken();
    if ($expect === '') err('服务端未配置令牌（deploy.token 或 LQ_DEPLOY_TOKEN）');
    if ($Token === '' || !hash_equals($expect, $Token)) err('令牌错误');

    $abs = $Path === '' ? $ROOT : resolvePath($ROOT, $Path);
    if ($abs === false) err('路径非法：不允许 ..、绝对路径');

    // 禁止区检查
    global $DENY;
    foreach ($DENY as $d) {
        $dAbs = $ROOT . '/' . $d;
        if ($abs === $dAbs || strpos($abs, $dAbs . '/') === 0) err('该路径受保护');
    }

    switch ($Action) {
        case 'write': {
            $content = $_POST['Content'] ?? null;
            if ($content === null && isset($_POST['ContentBase64'])) {
                $content = base64_decode($_POST['ContentBase64'], true);
                if ($content === false) err('ContentBase64 解码失败');
            }
            if ($content === null) err('缺少 Content');
            $dir = dirname($abs);
            if (!is_dir($dir) && !mkdir($dir, 0755, true)) err('无法创建目录');
            // 原子写入：临时文件 + rename
            $tmp = $abs . '.deploy_tmp_' . getmypid();
            if (file_put_contents($tmp, $content) === false) err('写入失败');
            if (!rename($tmp, $abs)) { @unlink($tmp); err('替换文件失败'); }
            out(['code' => 0, 'message' => 'ok', 'path' => $Path, 'size' => strlen($content)]);
        }

        case 'read': {
            if (!is_file($abs)) err('文件不存在');
            out(['code' => 0, 'path' => $Path, 'content' => file_get_contents($abs)]);
        }

        case 'list': {
            if (!is_dir($abs)) err('目录不存在');
            $items = [];
            foreach (scandir($abs) as $e) {
                if ($e === '.' || $e === '..') continue;
                $p = $abs . '/' . $e;
                $items[] = ['name' => $e, 'type' => is_dir($p) ? 'dir' : 'file', 'size' => is_file($p) ? filesize($p) : 0];
            }
            out(['code' => 0, 'path' => $Path, 'items' => $items]);
        }

        case 'delete': {
            if (!is_file($abs)) err('文件不存在');
            if (!unlink($abs)) err('删除失败');
            out(['code' => 0, 'message' => 'ok', 'path' => $Path]);
        }

        case 'info': {
            out(['code' => 0, 'root' => $ROOT, 'php' => PHP_VERSION, 'time' => date('c')]);
        }

        case 'sql': {
            // 参数: Db（白名单内）, Sql（单条语句）
            $Db  = trim($_POST['Db'] ?? '');
            $Sql = $_POST['Sql'] ?? '';
            if ($msg = sqlGuard($Db, $Sql, $DB_WHITELIST)) err($msg);

            $pdo = new PDO(
                "mysql:host=$DB_HOST;port=$DB_PORT;dbname=$Db;charset=utf8mb4",
                $DB_USER,
                loadDbPass(),
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
            );

            $s = trim($Sql);
            if (preg_match('/^(select|show|describe|desc)\b/i', $s)) {
                $rows = $pdo->query($s)->fetchAll();
                $truncated = count($rows) > 500;
                if ($truncated) $rows = array_slice($rows, 0, 500);
                out(['code' => 0, 'count' => count($rows), 'truncated' => $truncated, 'rows' => $rows]);
            }
            out(['code' => 0, 'message' => 'ok', 'affected' => $pdo->exec($s)]);
        }

        default:
            err('action 必须是 write / read / list / delete / info / sql');
    }
} catch (Throwable $e) {
    http_response_code(500);
    out(['code' => 1, 'message' => $e->getMessage()]);
}
