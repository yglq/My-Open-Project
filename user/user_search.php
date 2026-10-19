<?php
/**
 * 搜索用户
 * POST: UserId, Keyword
 * 返回：匹配的昵称/ID 用户列表
 */
require_once __DIR__ . '/../lib/db.php';
header('Content-Type: application/json; charset=utf-8');

try {

    requirePost();

    $UserId  = intval($_POST['UserId'] ?? 0);
    $Keyword = trim($_POST['Keyword'] ?? '');

    if ($UserId <= 0) throw new Exception('参数错误');
    if ($Keyword === '') throw new Exception('请输入搜索内容');

    $_pdo = GetPDO();

    // 判断是否是纯数字（搜索 user_id）
    if (ctype_digit($Keyword)) {
        $_stmt = $_pdo->prepare(
            "SELECT user_id, nickname, avatar, status, can_search 
             FROM users 
             WHERE user_id = :kw AND user_id != :uid AND status = 0 AND can_search = 1 
             LIMIT 1"
        );
        $_stmt->execute(['kw' => intval($Keyword), 'uid' => $UserId]);
    } else {
        // 模糊搜索昵称
        $_stmt = $_pdo->prepare(
            "SELECT user_id, nickname, avatar, status, can_search 
             FROM users 
             WHERE nickname LIKE :kw AND user_id != :uid AND status = 0 AND can_search = 1 
             LIMIT 10"
        );
        $_stmt->execute(['kw' => '%' . $Keyword . '%', 'uid' => $UserId]);
    }

    $_users = $_stmt->fetchAll();

    $_result = [];
    foreach ($_users as $_u) {
        $_result[] = [
            'user_id'  => $_u['user_id'],
            'nickname' => $_u['nickname'] ?: $_u['username'],
            'avatar'   => $_u['avatar'] ?: 'icon.png'
        ];
    }

    echo json_encode(['code' => 0, 'data' => $_result], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}