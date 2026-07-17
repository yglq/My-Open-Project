-- ========== LQ Cloud Console 数据库重置脚本 ==========
-- 用法: mysql -u root -p'123456' -h 127.0.0.1 < reset_db.sql

USE game_data;

-- 清空核心表并重置自增
TRUNCATE TABLE users;
TRUNCATE TABLE data_tables_registry;
TRUNCATE TABLE admin_config;
TRUNCATE TABLE chat_data;

-- 删除 user_data 中的所有子用户表
SELECT CONCAT('DROP TABLE IF EXISTS user_data.`', table_name, '`;')
FROM information_schema.tables
WHERE table_schema = 'user_data'
INTO OUTFILE '/tmp/drop_tables.sql';

SOURCE /tmp/drop_tables.sql;