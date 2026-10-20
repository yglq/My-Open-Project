-- Tokey 独立字段：admin_config 加 tokey 列，配置页“服务器相关”区块可查看/修改
-- 用法：mysql -u 用户 -p game_data < migrations/003_db_credentials.sql

ALTER TABLE `admin_config` ADD COLUMN `tokey` VARCHAR(64) NOT NULL DEFAULT '';
