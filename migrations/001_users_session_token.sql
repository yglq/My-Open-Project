-- 会话令牌字段：登录态不再依赖前端存明文密码
ALTER TABLE `users` ADD COLUMN `session_token` VARCHAR(64) NOT NULL DEFAULT '' AFTER `password`;
