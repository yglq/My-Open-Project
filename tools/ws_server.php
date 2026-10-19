<?php
/**
 * WebSocket 实时推送守护进程（仅 CLI 运行，勿通过 Web 访问）
 *
 *   php tools/ws_server.php          # 前台运行
 *   tools/ws_ctl.php action=start    # 后台拉起（网页接口）
 *
 * 监听 0.0.0.0:2081，同一个端口上提供两个入口：
 *   GET  /ws?uid=<用户ID>&token=<SessionToken>   —— 浏览器 WebSocket 握手
 *   POST /publish  (仅 127.0.0.1，需 X-Internal-Token 头)
 *        body: {"member_ids":[1,2],"payload":{...}}  —— 把 payload 推给在线成员
 *
 * 纯 PHP socket 实现，不依赖 composer/扩展。
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
set_time_limit(0);
ignore_user_abort(true);

require_once __DIR__ . '/../lib/db.php';

const WS_PORT = 2081;
const INTERNAL_TOKEN_FILE = __DIR__ . '/ws_internal.token';
const PID_FILE = __DIR__ . '/ws_server.pid';
const LOG_FILE = __DIR__ . '/ws.log';

// ---- 内部推送令牌：首次启动自动生成，不落库不进 git ----
if (!is_file(INTERNAL_TOKEN_FILE)) {
    file_put_contents(INTERNAL_TOKEN_FILE, bin2hex(random_bytes(20)));
    @chmod(INTERNAL_TOKEN_FILE, 0600);
}
$InternalToken = trim(file_get_contents(INTERNAL_TOKEN_FILE));

file_put_contents(PID_FILE, (string)getmypid());

function ws_log($msg) { @file_put_contents(LOG_FILE, date('Y-m-d H:i:s') . " $msg\n", FILE_APPEND); }

// ---- 鉴权：uid + session_token 查 users 表 ----
function ws_auth($uid, $token) {
    $uid = intval($uid);
    if ($uid <= 0 || $token === '') return 0;
    try {
        $pdo = GetPDO();
        $st = $pdo->prepare("SELECT user_id FROM users WHERE user_id = :u AND session_token = :t AND session_token <> '' LIMIT 1");
        $st->execute(['u' => $uid, 't' => $token]);
        return $st->fetch() ? $uid : 0;
    } catch (Throwable $e) {
        // 长驻进程的 PDO 可能被 MySQL wait_timeout 断掉，重连一次
        try {
            $pdo = new PDO(
                'mysql:host=' . (getenv('LQ_DB_HOST') ?: '127.0.0.1') . ';port=' . (getenv('LQ_DB_PORT') ?: '3306') . ';dbname=' . (getenv('LQ_DB_NAME') ?: 'game_data') . ';charset=utf8mb4',
                getenv('LQ_DB_USER') ?: 'root',
                getenv('LQ_DB_PASS') ?: '123456',
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
            );
            $st = $pdo->prepare("SELECT user_id FROM users WHERE user_id = :u AND session_token = :t AND session_token <> '' LIMIT 1");
            $st->execute(['u' => $uid, 't' => $token]);
            return $st->fetch() ? $uid : 0;
        } catch (Throwable $e2) {
            ws_log('auth db error: ' . $e2->getMessage());
            return 0;
        }
    }
}

// ---- WebSocket 帧 ----
function ws_send_text($sock, $payload) {
    $len = strlen($payload);
    if ($len < 126) {
        $head = chr(0x81) . chr($len);
    } elseif ($len < 65536) {
        $head = chr(0x81) . chr(126) . pack('n', $len);
    } else {
        $head = chr(0x81) . chr(127) . pack('NN', 0, $len);
    }
    $data = $head . $payload;
    $total = strlen($data);
    $sent = 0;
    for ($i = 0; $i < 200 && $sent < $total; $i++) {
        $n = @socket_write($sock, substr($data, $sent));
        if ($n === false) return false;
        if ($n === 0) { usleep(5000); continue; }
        $sent += $n;
    }
    return $sent >= $total;
}

// 从缓冲区解析一个完整帧；返回 [opcode, payload, consumed] 或 null（数据不够）
function ws_parse_frame($buf) {
    $blen = strlen($buf);
    if ($blen < 2) return null;
    $b1 = ord($buf[0]); $b2 = ord($buf[1]);
    $opcode = $b1 & 0x0F;
    $masked = ($b2 & 0x80) !== 0;
    $len = $b2 & 0x7F;
    $pos = 2;
    if ($len === 126) {
        if ($blen < 4) return null;
        $len = unpack('n', substr($buf, 2, 2))[1];
        $pos = 4;
    } elseif ($len === 127) {
        if ($blen < 10) return null;
        $u = unpack('N2', substr($buf, 2, 8));
        $len = $u[2]; // 消息都很小，高 32 位忽略
        $pos = 10;
    }
    $mask = '';
    if ($masked) {
        if ($blen < $pos + 4) return null;
        $mask = substr($buf, $pos, 4);
        $pos += 4;
    }
    if ($blen < $pos + $len) return null;
    $payload = substr($buf, $pos, $len);
    if ($masked) {
        for ($i = 0; $i < $len; $i++) $payload[$i] = chr(ord($payload[$i]) ^ ord($mask[$i % 4]));
    }
    return [$opcode, $payload, $pos + $len];
}

// ---- 主循环 ----
$listen = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_set_option($listen, SOL_SOCKET, SO_REUSEADDR, 1);
if (!@socket_bind($listen, '0.0.0.0', WS_PORT)) {
    ws_log('bind failed: ' . socket_strerror(socket_last_error()));
    exit(1);
}
socket_listen($listen, 64);
socket_set_nonblock($listen);
ws_log("ws server started on :" . WS_PORT . " pid=" . getmypid());

$clients = []; // id => ['sock'=>, 'buf'=>'', 'hs'=>false, 'uid'=>0, 'is_http'=>false]

$closeClient = function ($id) use (&$clients) {
    if (isset($clients[$id])) {
        @socket_shutdown($clients[$id]['sock']);
        @socket_close($clients[$id]['sock']);
        unset($clients[$id]);
    }
};

while (true) {
    $read = [$listen];
    foreach ($clients as $c) $read[] = $c['sock'];
    $w = null; $e = null;
    @socket_select($read, $w, $e, 10);

    if (in_array($listen, $read, true)) {
        $ns = @socket_accept($listen);
        if ($ns) {
            socket_set_nonblock($ns);
            $clients[spl_object_id($ns)] = ['sock' => $ns, 'buf' => '', 'hs' => false, 'uid' => 0, 'is_http' => false];
        }
    }

    foreach ($read as $s) {
        if ($s === $listen) continue;
        $id = spl_object_id($s);
        if (!isset($clients[$id])) continue;
        $data = @socket_read($s, 8192);
        if ($data === false || $data === '') { $closeClient($id); continue; }
        $clients[$id]['buf'] .= $data;
        if (strlen($clients[$id]['buf']) > 1024 * 1024) { $closeClient($id); continue; }

        // ---- 阶段 1：HTTP 握手 / publish ----
        if (!$clients[$id]['hs']) {
            $hdrEnd = strpos($clients[$id]['buf'], "\r\n\r\n");
            if ($hdrEnd === false) continue;
            $header = substr($clients[$id]['buf'], 0, $hdrEnd);
            $rest = substr($clients[$id]['buf'], $hdrEnd + 4);
            $lines = explode("\r\n", $header);
            $reqLine = array_shift($lines);
            $headers = [];
            foreach ($lines as $l) {
                $p = strpos($l, ':');
                if ($p !== false) $headers[strtolower(trim(substr($l, 0, $p)))] = trim(substr($l, $p + 1));
            }
            $parts = explode(' ', $reqLine);
            $method = strtoupper($parts[0] ?? '');
            $path = $parts[1] ?? '/';

            if ($method === 'GET' && strpos($path, '/ws') === 0) {
                // WebSocket 握手
                $query = [];
                $q = strpos($path, '?');
                if ($q !== false) parse_str(substr($path, $q + 1), $query);
                $uid = ws_auth($query['uid'] ?? 0, $query['token'] ?? '');
                $key = $headers['sec-websocket-key'] ?? '';
                if ($uid <= 0 || $key === '' || strtolower($headers['upgrade'] ?? '') !== 'websocket') {
                    @socket_write($s, "HTTP/1.1 401 Unauthorized\r\nContent-Length: 0\r\n\r\n");
                    $closeClient($id);
                    continue;
                }
                $accept = base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
                $resp = "HTTP/1.1 101 Switching Protocols\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Accept: $accept\r\n\r\n";
                @socket_write($s, $resp);
                $clients[$id]['hs'] = true;
                $clients[$id]['uid'] = $uid;
                $clients[$id]['buf'] = $rest;
            } elseif ($method === 'POST' && strpos($path, '/publish') === 0) {
                // 内部推送：仅本机 + 令牌
                socket_getpeername($s, $peerIp);
                $contentLen = intval($headers['content-length'] ?? 0);
                if ($contentLen <= 0 || $contentLen > 512 * 1024) { $closeClient($id); continue; }
                if (strlen($rest) < $contentLen) { $clients[$id]['buf'] = $clients[$id]['buf']; continue; } // 等 body
                $body = substr($rest, 0, $contentLen);
                $ok = ($peerIp === '127.0.0.1' || $peerIp === '::1')
                    && hash_equals($InternalToken, $headers['x-internal-token'] ?? '');
                $pushed = 0;
                if ($ok) {
                    $req = json_decode($body, true);
                    $targets = array_map('intval', $req['member_ids'] ?? []);
                    ws_log('publish targets=' . json_encode($targets) . ' online=' . json_encode(array_map(function ($c) { return [$c['uid'], $c['hs']]; }, $clients)));
                    $text = json_encode($req['payload'] ?? new stdClass(), JSON_UNESCAPED_UNICODE);
                    foreach ($clients as $cid => $c) {
                        if ($c['hs'] && $c['uid'] > 0 && in_array($c['uid'], $targets, true)) {
                            if (ws_send_text($c['sock'], $text)) $pushed++;
                            else $closeClient($cid);
                        }
                    }
                }
                $respBody = json_encode(['code' => $ok ? 0 : 1, 'pushed' => $pushed]);
                @socket_write($s, "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nContent-Length: " . strlen($respBody) . "\r\n\r\n" . $respBody);
                $closeClient($id);
                continue;
            } else {
                @socket_write($s, "HTTP/1.1 404 Not Found\r\nContent-Length: 0\r\n\r\n");
                $closeClient($id);
                continue;
            }
        }

        // ---- 阶段 2：WebSocket 帧 ----
        while ($clients[$id]['hs'] && ($frame = ws_parse_frame($clients[$id]['buf'])) !== null) {
            [$opcode, $payload, $consumed] = $frame;
            $clients[$id]['buf'] = substr($clients[$id]['buf'], $consumed);
            if ($opcode === 8) { $closeClient($id); break; }          // close
            if ($opcode === 9) {                                     // ping → pong
                $len = strlen($payload);
                ws_send_text_pong($s, $payload);
                continue;
            }
            if ($opcode === 1 || $opcode === 2) {
                $msg = json_decode($payload, true);
                if (is_array($msg) && ($msg['t'] ?? '') === 'ping') {
                    ws_send_text($s, '{"t":"pong"}');
                }
            }
        }
    }
}

// opcode 0xA pong 帧
function ws_send_text_pong($sock, $payload) {
    $len = strlen($payload);
    if ($len > 125) $payload = substr($payload, 0, 125);
    @socket_write($sock, chr(0x8A) . chr(strlen($payload)) . $payload);
}
