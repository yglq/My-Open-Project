-- 002: 聊天系统标准化——三表结构替代整行 chat_json
-- chat_conversations 会话 / chat_members 成员与已读进度 / chat_messages 消息

CREATE TABLE IF NOT EXISTS `chat_conversations` (
  `chat_id`      VARCHAR(32)  NOT NULL,
  `is_group`     TINYINT(1)   NOT NULL DEFAULT 0,
  `group_name`   VARCHAR(64)  NOT NULL DEFAULT '',
  `group_avatar` VARCHAR(128) NOT NULL DEFAULT '',
  `owner_id`     INT          NOT NULL DEFAULT 0,
  `created_at`   DATETIME     NOT NULL,
  PRIMARY KEY (`chat_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `chat_members` (
  `chat_id`      VARCHAR(32) NOT NULL,
  `user_id`      INT         NOT NULL,
  `member_name`  VARCHAR(64) NOT NULL DEFAULT '',
  `last_read_id` BIGINT      NOT NULL DEFAULT 0,
  `joined_at`    DATETIME    NOT NULL,
  PRIMARY KEY (`chat_id`, `user_id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `chat_messages` (
  `msg_id`     BIGINT      NOT NULL AUTO_INCREMENT,
  `chat_id`    VARCHAR(32) NOT NULL,
  `sender_id`  INT         NOT NULL,
  `mtype`      VARCHAR(20) NOT NULL DEFAULT 'Message',
  `body`       TEXT        NOT NULL,
  `created_at` DATETIME    NOT NULL,
  PRIMARY KEY (`msg_id`),
  KEY `idx_chat` (`chat_id`, `msg_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 旧表加迁移标记（数据保留不删）
ALTER TABLE `chat_data` ADD COLUMN IF NOT EXISTS `migrated` TINYINT(1) NOT NULL DEFAULT 0;
