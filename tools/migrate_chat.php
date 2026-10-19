<?php
/**
 * 聊天数据迁移：chat_data.chat_json → chat_conversations/chat_members/chat_messages
 * POST: Token（deploy.token / LQ_DEPLOY_TOKEN）
 * 幂等：只处理 migrated=0 的行，可重复执行。
 */
require_once __DIR__ . '/../lib/db.php';
header('Content-Type: application/json; charset=utf-8');

try {
    requirePost();

    $Token = trim($_POST['Token'] ?? '');
    $Expected = trim(@file_get_contents(__DIR__ . '/deploy.token') ?: '');
    $EnvToken = getenv('LQ_DEPLOY_TOKEN') ?: '';
    if ($Token === '' || ($Token !== $Expected && $Token !== $EnvToken)) {
        throw new Exception('令牌无效');
    }

    $_pdo = GetPDO();

    // 建新表（幂等）
    $_pdo->exec("CREATE TABLE IF NOT EXISTS `chat_conversations` (
        `chat_id` VARCHAR(32) NOT NULL PRIMARY KEY,
        `is_group` TINYINT(1) NOT NULL DEFAULT 0,
        `group_name` VARCHAR(64) NOT NULL DEFAULT '',
        `group_avatar` VARCHAR(128) NOT NULL DEFAULT '',
        `owner_id` INT NOT NULL DEFAULT 0,
        `created_at` DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $_pdo->exec("CREATE TABLE IF NOT EXISTS `chat_members` (
        `chat_id` VARCHAR(32) NOT NULL,
        `user_id` INT NOT NULL,
        `member_name` VARCHAR(64) NOT NULL DEFAULT '',
        `last_read_id` BIGINT NOT NULL DEFAULT 0,
        `joined_at` DATETIME NOT NULL,
        PRIMARY KEY (`chat_id`,`user_id`), KEY `idx_user` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $_pdo->exec("CREATE TABLE IF NOT EXISTS `chat_messages` (
        `msg_id` BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `chat_id` VARCHAR(32) NOT NULL,
        `sender_id` INT NOT NULL,
        `mtype` VARCHAR(20) NOT NULL DEFAULT 'Message',
        `body` TEXT NOT NULL,
        `created_at` DATETIME NOT NULL,
        KEY `idx_chat` (`chat_id`,`msg_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    try {
        $_pdo->exec("ALTER TABLE `chat_data` ADD COLUMN `migrated` TINYINT(1) NOT NULL DEFAULT 0");
    } catch (Throwable $e) { /* 列已存在 */ }

    $rows = $_pdo->query("SELECT chat_id, chat_json FROM chat_data WHERE migrated = 0")->fetchAll();
    $migrated = 0; $msgTotal = 0; $errors = [];

    foreach ($rows as $row) {
        $chatId = $row['chat_id'];
        $json = json_decode($row['chat_json'] ?? '[]', true);
        if (!is_array($json)) $json = [];

        $members  = $json['Members'] ?? [];
        $messages = $json['Messages'] ?? [];
        $readList = $json['ReadList'] ?? [];
        $isGroup  = count($members) > 2 ? 1 : 0;
        $ownerId  = $isGroup ? intval(array_key_first($members) ?: 0) : 0;

        $_pdo->beginTransaction();
        try {
            $_pdo->prepare("INSERT IGNORE INTO chat_conversations (chat_id,is_group,group_name,group_avatar,owner_id,created_at) VALUES (?,?,?,?,?,NOW())")
                 ->execute([$chatId, $isGroup, $json['GroupName'] ?? '', $json['GroupAvatar'] ?? '', $ownerId]);

            // 消息按旧 MsgId 升序插入，保证新自增 id 顺序一致；记录 旧MsgId→新msg_id 映射
            usort($messages, function ($a, $b) { return intval($a['MsgId'] ?? 0) <=> intval($b['MsgId'] ?? 0); });
            $idMap = [];
            $ins = $_pdo->prepare("INSERT INTO chat_messages (chat_id,sender_id,mtype,body,created_at) VALUES (?,?,?,?,?)");
            foreach ($messages as $m) {
                $oldId = intval($m['MsgId'] ?? 0);
                $body = $m;
                unset($body['MsgId'], $body['SenderId'], $body['Type'], $body['Time']);
                // created_at 用原 Time（旧格式 Y-m-d-H-i-s），缺失用当前时间
                $t = $m['Time'] ?? '';
                $dt = preg_match('/^\d{4}-\d{2}-\d{2}-\d{2}-\d{2}-\d{2}$/', $t)
                    ? DateTime::createFromFormat('Y-m-d-H-i-s', $t)->format('Y-m-d H:i:s')
                    : date('Y-m-d H:i:s');
                $ins->execute([$chatId, intval($m['SenderId'] ?? 0), $m['Type'] ?? 'Message', json_encode($body, JSON_UNESCAPED_UNICODE), $dt]);
                $idMap[$oldId] = intval($_pdo->lastInsertId());
                $msgTotal++;
            }
            $maxNewId = $idMap ? max($idMap) : 0;

            $insM = $_pdo->prepare("INSERT IGNORE INTO chat_members (chat_id,user_id,member_name,last_read_id,joined_at) VALUES (?,?,?,?,NOW())");
            foreach ($members as $uid => $name) {
                $oldRead = intval($readList[strval($uid)] ?? 0);
                $newRead = $idMap[$oldRead] ?? ($oldRead > 0 ? $maxNewId : 0);
                $insM->execute([$chatId, intval($uid), (string)$name, $newRead]);
            }

            $_pdo->prepare("UPDATE chat_data SET migrated = 1 WHERE chat_id = ?")->execute([$chatId]);
            $_pdo->commit();
            $migrated++;
        } catch (Throwable $e2) {
            if ($_pdo->inTransaction()) $_pdo->rollBack();
            $errors[] = "$chatId: " . $e2->getMessage();
        }
    }

    echo json_encode(['code' => 0, 'migrated' => $migrated, 'messages' => $msgTotal, 'errors' => $errors], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}
