<?php
/**
 * 发送消息
 * POST: UserId, ChatId, Type, Text/Num/ConfigId/StudioName...
 * 新结构：消息一行一条写入 chat_messages
 */
require_once __DIR__ . '/../lib/db.php';
header('Content-Type: application/json; charset=utf-8');

try {

    requirePost();
    authUser();

    $UserId = intval($_POST['UserId'] ?? 0);
    $ChatId = trim($_POST['ChatId'] ?? '');
    $Type   = trim($_POST['Type'] ?? 'Message');

    if ($UserId <= 0 || $ChatId === '') throw new Exception('参数错误');

    $_pdo = GetPDO();

    if (!chatIsMember($_pdo, $ChatId, $UserId)) {
        throw new Exception('无权发送消息');
    }

    // 消息体（类型相关字段存 body JSON）
    $_body = [];

    switch ($Type) {
        case 'Message':
            $_body['Text'] = trim($_POST['Text'] ?? '');
            if ($_body['Text'] === '') throw new Exception('消息内容不能为空');
            break;

        case 'Image':
            if (empty($_FILES['Image']) || $_FILES['Image']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('请选择图片');
            }
            $_file = $_FILES['Image'];
            $_imgInfo = @getimagesize($_file['tmp_name']);
            if ($_imgInfo === false) throw new Exception('文件不是有效的图片');
            if ($_file['size'] > 5 * 1024 * 1024) throw new Exception('图片不能超过 5MB');

            $_imgDir = __DIR__ . '/../chat_images/' . $ChatId;
            if (!is_dir($_imgDir)) mkdir($_imgDir, 0755, true);

            $_imgName = '';
            for ($_i = 0; $_i < 10; $_i++) {
                $_imgName = randomString(21, 'table') . '.png';
                if (!file_exists($_imgDir . '/' . $_imgName)) break;
            }
            if ($_imgName === '') throw new Exception('图片文件名生成失败');
            if (!move_uploaded_file($_file['tmp_name'], $_imgDir . '/' . $_imgName)) {
                throw new Exception('图片上传失败');
            }
            $_body['ImagePath'] = 'chat_images/' . $ChatId . '/' . $_imgName;
            break;

        case 'InviteStudio':
            $_body['ConfigId']   = trim($_POST['ConfigId'] ?? '');
            $_body['StudioName'] = trim($_POST['StudioName'] ?? '');
            if ($_body['ConfigId'] === '' || $_body['StudioName'] === '') throw new Exception('参数不全');
            break;

        case 'SendCoin':
        case 'RequestCoin':
            $_body['Num']         = intval($_POST['Num'] ?? 0);
            $_body['IsReceive']   = false;
            $_body['ReceiveTime'] = '';
            if ($_body['Num'] <= 0) throw new Exception('金币数量无效');
            break;

        default:
            throw new Exception('未知消息类型');
    }

    // 送金币先原子扣款，再插消息（事务保证）
    $_pdo->beginTransaction();
    try {
        if ($Type === 'SendCoin') {
            $_stmt4 = $_pdo->prepare("UPDATE users SET gold = gold - :num WHERE user_id = :uid AND gold >= :num");
            $_stmt4->execute(['num' => $_body['Num'], 'uid' => $UserId]);
            if ($_stmt4->rowCount() === 0) throw new Exception('金币不足');
        }

        $_stmt = $_pdo->prepare("INSERT INTO chat_messages (chat_id, sender_id, mtype, body, created_at) VALUES (:c,:s,:t,:b,NOW())");
        $_stmt->execute([
            'c' => $ChatId,
            's' => $UserId,
            't' => $Type,
            'b' => json_encode($_body, JSON_UNESCAPED_UNICODE),
        ]);
        $_newMsgId = intval($_pdo->lastInsertId());

        $_pdo->commit();
    } catch (Exception $e2) {
        if ($_pdo->inTransaction()) $_pdo->rollBack();
        throw $e2;
    }

    $_newMsg = array_merge([
        'MsgId'    => $_newMsgId,
        'SenderId' => $UserId,
        'Type'     => $Type,
        'Time'     => date('Y-m-d-H-i-s'),
    ], $_body);

    // 实时推送给在线成员
    wsPublishToMembers(chatMemberIds($_pdo, $ChatId), [
        'type'    => 'new_msg',
        'chat_id' => $ChatId,
        'msg'     => $_newMsg,
    ]);

    echo json_encode([
        'code'    => 0,
        'message' => '发送成功',
        'msg_id'  => $_newMsgId
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}
