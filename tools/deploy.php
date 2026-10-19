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

        default:
            err('action 必须是 write / read / list / delete / info');
    }
} catch (Throwable $e) {
    http_response_code(500);
    out(['code' => 1, 'message' => $e->getMessage()]);
}
