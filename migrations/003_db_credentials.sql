-- 数据库密钥独立化：Tokey 从 registry JSON 中独立出来单独存储
-- 用法：mysql -u 用户 -p game_data < migrations/003_db_credentials.sql

CREATE TABLE IF NOT EXISTS `db_credentials` (
  `config_id`  varchar(32)  NOT NULL,
  `tokey`      varchar(64)  NOT NULL,
  `owner_id`   int(11)      NOT NULL DEFAULT 0,
  `created_at` datetime     NOT NULL,
  PRIMARY KEY (`config_id`),
  UNIQUE KEY `uk_tokey` (`tokey`),
  KEY `idx_owner` (`owner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
