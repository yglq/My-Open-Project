<?php
/**
 * 发送消息
 * POST: UserId, ChatId, Type, Text/Num/ConfigId/StudioName...
 */
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('请求方式错误');
    }

    $UserId   = intval($_POST['UserId'] ?? 0);
    $ChatId   = trim($_POST['ChatId'] ?? '');
    $Type     = trim($_POST['Type'] ?? 'Message');

    if ($UserId <= 0 || $ChatId === '') throw new Exception('参数错误');

    $_pdo = GetPDO();

    // 取对话
    $_stmt = $_pdo->prepare("SELECT chat_json FROM chat_data WHERE chat_id = :cid LIMIT 1");
    $_stmt->execute(['cid' => $ChatId]);
    $_chat = $_stmt->fetch();
    if (!$_chat) throw new Exception('对话不存在');

    $_chatJson = json_decode($_chat['chat_json'] ?? '[]', true);
    $_members = $_chatJson['Members'] ?? [];

    if (!array_key_exists(strval($UserId), $_members)) {
        throw new Exception('无权发送消息');
    }

    // 生成新消息
    $_totalMsg = intval($_chatJson['TotalMsg'] ?? 0);
    $_newMsgId = $_totalMsg + 1;
    $_newMsg = [
        'MsgId'    => $_newMsgId,
        'SenderId' => $UserId,
        'Type'     => $Type,
        'Time'     => date('Y-m-d-H-i-s')
    ];



    switch ($Type) {
        case 'Message':
            $_newMsg['Text'] = trim($_POST['Text'] ?? '');
            if ($_newMsg['Text'] === '') throw new Exception('消息内容不能为空');
            break;

    
            case 'Image':
    if (empty($_FILES['Image']) || $_FILES['Image']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('请选择图片');
    }
    $_file = $_FILES['Image'];

    // 用 getimagesize 验证是否为图片
    $_imgInfo = @getimagesize($_file['tmp_name']);
    if ($_imgInfo === false) {
        throw new Exception('文件不是有效的图片');
    }

    // 限制大小 5MB
    if ($_file['size'] > 5 * 1024 * 1024) {
        throw new Exception('图片不能超过 5MB');
    }

    // 创建文件夹
    $_imgDir = __DIR__ . '/../chat_images/' . $ChatId;
    if (!is_dir($_imgDir)) {
        mkdir($_imgDir, 0755, true);
    }

    // 生成随机文件名
    $_imgName = '';
    for ($_i = 0; $_i < 10; $_i++) {
        $_imgName = randomString(21, 'table') . '.png';
        if (!file_exists($_imgDir . '/' . $_imgName)) break;
    }
    if ($_imgName === '') throw new Exception('图片文件名生成失败');

    if (!move_uploaded_file($_file['tmp_name'], $_imgDir . '/' . $_imgName)) {
        throw new Exception('图片上传失败');
    }

    $_newMsg['ImagePath'] = 'chat_images/' . $ChatId . '/' . $_imgName;
    break;
case 'InviteStudio':
    $_newMsg['ConfigId']   = trim($_POST['ConfigId'] ?? '');
    $_newMsg['StudioName'] = trim($_POST['StudioName'] ?? '');
    if ($_newMsg['ConfigId'] === '' || $_newMsg['StudioName'] === '')
        throw new Exception('参数不全');
    break;
    
    case 'SendCoin':
    $_newMsg['Num']       = intval($_POST['Num'] ?? 0);
    $_newMsg['IsReceive'] = false;
    $_newMsg['ReceiveTime'] = '';
    if ($_newMsg['Num'] <= 0) throw new Exception('金币数量无效');

    // 扣钱
    $_stmt3 = $_pdo->prepare("SELECT gold FROM users WHERE user_id = :uid LIMIT 1");
    $_stmt3->execute(['uid' => $UserId]);
    $_myGold = intval($_stmt3->fetchColumn());
    if ($_myGold < $_newMsg['Num']) throw new Exception('金币不足');

    $_stmt4 = $_pdo->prepare("UPDATE users SET gold = gold - :num WHERE user_id = :uid");
    $_stmt4->execute(['num' => $_newMsg['Num'], 'uid' => $UserId]);
    break;
       case 'RequestCoin':
            $_newMsg['Num']       = intval($_POST['Num'] ?? 0);
            $_newMsg['IsReceive'] = false;
            $_newMsg['ReceiveTime'] = '';
            if ($_newMsg['Num'] <= 0) throw new Exception('金币数量无效');
            break;

        default:
            throw new Exception('未知消息类型');
    }

    // 插入消息到 Messages 数组开头（倒序）
    array_unshift($_chatJson['Messages'], $_newMsg);
    $_chatJson['TotalMsg'] = $_newMsgId;

    $_newJson = json_encode($_chatJson, JSON_UNESCAPED_UNICODE);
    $_stmt2 = $_pdo->prepare("UPDATE chat_data SET chat_json = :json WHERE chat_id = :cid");
    $_stmt2->execute(['json' => $_newJson, 'cid' => $ChatId]);

    echo json_encode([
        'code'    => 0,
        'message' => '发送成功',
        'msg_id'  => $_newMsgId
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}