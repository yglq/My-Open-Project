<?php
/**
 * 接受金币（红包/转账）
 * POST: UserId, ChatId, MsgId
 */
require_once __DIR__ . '/../lib/db.php';
header('Content-Type: application/json; charset=utf-8');

try {

    requirePost();
    authUser();

    $UserId = intval($_POST['UserId'] ?? 0);
    $ChatId = trim($_POST['ChatId'] ?? '');
    $MsgId  = intval($_POST['MsgId'] ?? 0);

    if ($UserId <= 0 || $ChatId === '' || $MsgId <= 0) throw new Exception('参数错误');

    $_pdo = GetPDO();

    // 取对话
    $_stmt = $_pdo->prepare("SELECT chat_json FROM chat_data WHERE chat_id = :cid LIMIT 1");
    $_stmt->execute(['cid' => $ChatId]);
    $_chat = $_stmt->fetch();
    if (!$_chat) throw new Exception('对话不存在');

    $_chatJson = json_decode($_chat['chat_json'] ?? '[]', true);
    $_members = $_chatJson['Members'] ?? [];
    $_messages = $_chatJson['Messages'] ?? [];
    $_isGroup = count($_members) > 2;

    if (!array_key_exists(strval($UserId), $_members)) {
        throw new Exception('无权操作');
    }

    // 找到目标消息
    $_targetMsg = null;
    $_msgIndex = -1;
    foreach ($_messages as $_i => $_msg) {
        if (intval($_msg['MsgId']) === $MsgId) {
            $_targetMsg = $_msg;
            $_msgIndex = $_i;
            break;
        }
    }

    if (!$_targetMsg) throw new Exception('消息不存在');

    $_type = $_targetMsg['Type'] ?? '';
    if ($_type !== 'SendCoin' && $_type !== 'RequestCoin') {
        throw new Exception('不是金币消息');
    }

    // 群聊红包：已被领取
    if ($_isGroup && $_type === 'SendCoin' && ($_targetMsg['IsReceive'] ?? false)) {
        throw new Exception('红包已被领取');
    }

    // 私聊转账：已被领取
    if (!$_isGroup && ($_targetMsg['IsReceive'] ?? false)) {
        throw new Exception('已被领取');
    }

    // 不能自己领自己发的
    if (intval($_targetMsg['SenderId']) === $UserId) {
        throw new Exception('不能领取自己发的金币');
    }

    $Num = intval($_targetMsg['Num'] ?? 0);
    if ($Num <= 0) throw new Exception('金币数量无效');
// 索要金币：接收者（点接受的人）付钱给发送者
if ($_type === 'RequestCoin') {
    // 检查余额
    $_stmt2 = $_pdo->prepare("SELECT gold FROM users WHERE user_id = :uid LIMIT 1");
    $_stmt2->execute(['uid' => $UserId]);
    $_myGold = intval($_stmt2->fetchColumn());
    if ($_myGold < $Num) throw new Exception('金币不足');

    // 扣当前用户的钱
    $_stmt3 = $_pdo->prepare("UPDATE users SET gold = gold - :num WHERE user_id = :uid");
    $_stmt3->execute(['num' => $Num, 'uid' => $UserId]);

    // 加给发送者
    $_stmt4 = $_pdo->prepare("UPDATE users SET gold = gold + :num WHERE user_id = :uid");
    $_stmt4->execute(['num' => $Num, 'uid' => $_targetMsg['SenderId']]);
}


    // 赠送金币：发送者已经扣过了（发送时扣），接收者直接收
    if ($_type === 'SendCoin') {
        $_stmt5 = $_pdo->prepare("UPDATE users SET gold = gold + :num WHERE user_id = :uid");
        $_stmt5->execute(['num' => $Num, 'uid' => $UserId]);
    }

    // 标记已领取
    $_chatJson['Messages'][$_msgIndex]['IsReceive'] = true;
    $_chatJson['Messages'][$_msgIndex]['ReceiveTime'] = date('Y-m-d-H-i-s');
    $_chatJson['Messages'][$_msgIndex]['ReceiveUserId'] = $UserId;

    $_newJson = json_encode($_chatJson, JSON_UNESCAPED_UNICODE);
    $_stmt6 = $_pdo->prepare("UPDATE chat_data SET chat_json = :json WHERE chat_id = :cid");
    $_stmt6->execute(['json' => $_newJson, 'cid' => $ChatId]);

    // 实时刷新在线成员界面（静默失败）
    wsPublishToMembers(array_map('intval', array_keys($_members)), ['type' => 'chat_refresh', 'chat_id' => $ChatId]);

    echo json_encode([
        'code'    => 0,
        'message' => '领取成功',
        'num'     => $Num
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}