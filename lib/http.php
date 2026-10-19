<?php
// HTTP 层公共助手
// 所有端点 require db.php 后自动可用：requirePost() / apiOk() / postParam()

// 仅允许 POST 请求（对外只读接口允许 GET+POST 的，不要用这个）
function requirePost() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('仅支持 POST 请求');
    }
}

// 统一成功响应并结束脚本
// apiOk(['data' => $x]) -> {"code":0,"data":...}
function apiOk(array $data = []) {
    echo json_encode(array_merge(['code' => 0], $data), JSON_UNESCAPED_UNICODE);
    exit;
}

// 取 POST 参数（字符串自动 trim）
// postParam('ConfigId')          必填，为空抛异常
// postParam('Bio', false, '')    可选，缺省返回 ''
function postParam(string $name, bool $required = true, $default = '') {
    $v = $_POST[$name] ?? $default;
    if (is_string($v)) $v = trim($v);
    if ($required && ($v === '' || $v === null)) {
        throw new Exception("参数错误：{$name} 必填");
    }
    return $v;
}
