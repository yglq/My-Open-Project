<?php
/**
 * WebSocket 守护进程控制端点
 * POST: Token（同 deploy.token / LQ_DEPLOY_TOKEN）, Action=start|stop|status
 */
require_once __DIR__ . '/../lib/db.php';
header('Content-Type: application/json; charset=utf-8');

try {
    requirePost();

    $Token = trim($_POST['Token'] ?? '');
    $Action = trim($_POST['Action'] ?? 'status');
    $__sec = is_file(__DIR__ . '/secrets.php') ? (include __DIR__ . '/secrets.php') : [];
    $Expected = is_array($__sec) ? ($__sec['deploy_token'] ?? '') : '';
    if ($Expected === '') $Expected = trim(@file_get_contents(__DIR__ . '/deploy.token') ?: '');
    $EnvToken = getenv('LQ_DEPLOY_TOKEN') ?: '';
    if ($Token === '' || ($Token !== $Expected && $Token !== $EnvToken)) {
        throw new Exception('令牌无效');
    }

    $running = (bool)@fsockopen('127.0.0.1', 2081, $errno, $errstr, 0.3);

    if ($Action === 'start') {
        if (!$running) {
            // 找一个真正的 PHP CLI 二进制（PHP_BINARY 在 FPM 下是 php-fpm，跑不了脚本）
            $php = 'php';
            $cands = [];
            $which = trim((string)@shell_exec('command -v php 2>/dev/null'));
            if ($which !== '') $cands[] = $which;
            foreach (glob('/www/server/php/*/bin/php') ?: [] as $p) $cands[] = $p;
            $cands[] = '/usr/bin/php';
            $cands[] = '/usr/local/bin/php';
            $cands[] = PHP_BINARY;
            foreach (array_unique($cands) as $c) {
                if (!is_executable($c)) continue;
                $v = (string)@shell_exec(escapeshellarg($c) . ' -v 2>/dev/null');
                if (stripos($v, '(cli)') !== false) { $php = $c; break; }
            }
            $cmd = 'cd ' . escapeshellarg(dirname(__DIR__))
                . ' && nohup ' . escapeshellarg($php) . ' tools/ws_server.php >> tools/ws.log 2>&1 &';
            exec($cmd);
            // 等它起来
            for ($i = 0; $i < 20; $i++) {
                usleep(200000);
                if (@fsockopen('127.0.0.1', 2081, $e2, $s2, 0.2)) { $running = true; break; }
            }
        }
        echo json_encode(['code' => 0, 'running' => $running, 'message' => $running ? 'ws server running' : 'start failed, see tools/ws.log'], JSON_UNESCAPED_UNICODE);
    } elseif ($Action === 'stop') {
        $pid = intval(@file_get_contents(__DIR__ . '/ws_server.pid') ?: 0);
        if ($pid > 0 && function_exists('posix_kill')) @posix_kill($pid, 9);
        usleep(200000);
        $running = (bool)@fsockopen('127.0.0.1', 2081, $errno, $errstr, 0.3);
        echo json_encode(['code' => 0, 'running' => $running, 'message' => $running ? 'still running' : 'stopped'], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['code' => 0, 'running' => $running], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}
