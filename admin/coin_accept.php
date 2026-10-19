<?php
/**
 * 接受金币（红包/转账）
 * POST: UserId, ChatId, MsgId
 * 新结构：消息存 chat_messages，领取状态更新 body JSON
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

    if (!chatIsMember($_pdo, $ChatId, $UserId)) throw new Exception('无权操作');

    $_stmtC = $_pdo->prepare("SELECT is_group FROM chat_conversations WHERE chat_id = :c LIMIT 1");
    $_stmtC->execute(['c' => $ChatId]);
    $_conv = $_stmtC->fetch();
    if (!$_conv) throw new Exception('对话不存在');
    $_isGroup = intval($_conv['is_group']) === 1;

    $_stmtM = $_pdo->prepare("SELECT * FROM chat_messages WHERE msg_id = :m AND chat_id = :c LIMIT 1 FOR UPDATE");
    $_pdo->beginTransaction();
    $_stmtM->execute(['m' => $MsgId, 'c' => $ChatId]);
    $_row = $_stmtM->fetch();
    if (!$_row) { $_pdo->rollBack(); throw new Exception('消息不存在'); }

    $_targetMsg = chatRowToMsg($_row);
    $_type = $_targetMsg['Type'] ?? '';
    if ($_type !== 'SendCoin' && $_type !== 'RequestCoin') {
        $_pdo->rollBack();
        throw new Exception('不是金币消息');
    }
    if ($_targetMsg['IsReceive'] ?? false) {
        $_pdo->rollBack();
        throw new Exception($_isGroup && $_type === 'SendCoin' ? '红包已被领取' : '已被领取');
    }
    if (intval($_targetMsg['SenderId']) === $UserId) {
        $_pdo->rollBack();
        throw new Exception('不能领取自己发的金币');
    }

    $Num = intval($_targetMsg['Num'] ?? 0);
    if ($Num <= 0) { $_pdo->rollBack(); throw new Exception('金币数量无效'); }

    try {
        // 索要金币：接收者付钱给发送者
        if ($_type === 'RequestCoin') {
            $_stmt3 = $_pdo->prepare("UPDATE users SET gold = gold - :num WHERE user_id = :uid AND gold >= :num");
            $_stmt3->execute(['num' => $Num, 'uid' => $UserId]);
            if ($_stmt3->rowCount() === 0) throw new Exception('金币不足');
            $_stmt4 = $_pdo->prepare("UPDATE users SET gold = gold + :num WHERE user_id = :uid");
            $_stmt4->execute(['num' => $Num, 'uid' => intval($_targetMsg['SenderId'])]);
        }
        // 赠送金币：发送时已扣，接收者直接收
        if ($_type === 'SendCoin') {
            $_stmt5 = $_pdo->prepare("UPDATE users SET gold = gold + :num WHERE user_id = :uid");
            $_stmt5->execute(['num' => $Num, 'uid' => $UserId]);
        }

        // 标记已领取（只改这一条消息的 body）
        $_body = json_decode($_row['body'], true) ?: [];
        $_body['IsReceive'] = true;
        $_body['ReceiveTime'] = date('Y-m-d-H-i-s');
        $_body['ReceiveUserId'] = $UserId;
        $_pdo->prepare("UPDATE chat_messages SET body = :b WHERE msg_id = :m")
             ->execute(['b' => json_encode($_body, JSON_UNESCAPED_UNICODE), 'm' => $MsgId]);

        $_pdo->commit();
    } catch (Exception $e2) {
        if ($_pdo->inTransaction()) $_pdo->rollBack();
        throw $e2;
    }

    chatPushRefresh($_pdo, $ChatId);

    echo json_encode([
        'code'    => 0,
        'message' => '领取成功',
        'num'     => $Num
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}
