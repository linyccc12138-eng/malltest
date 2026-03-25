# 奇妙集市

基于 PHP 8 + MySQL + Tailwind CSS + Alpine.js 的微信网页电商网站，支持会员系统联动、余额支付、微信支付接入预留、用户中心、管理后台、分级日志与订单超时关闭。

## 目录说明
- `database/init_all.sql`：商城库与会员库初始化脚本。
- `docs/功能设计说明书.md`：正式版功能设计说明书。
- `docs/部署文档.md`：Linux 服务器部署文档。
- `public/`：Web 根目录。
- `scripts/order_timeout_worker.php`：订单超时关闭脚本。
- `需求文档及参考文件/`：原始会员系统代码、SQL、页面与参考图。

## 默认账号
- 管理员：`lyccc` 或 `13206335421` / `dyq42517`
- 普通用户：`demo_user / User@123`

## 快速开始
1. 复制 `.env.example` 为 `.env` 并填写数据库、Redis、域名配置。
2. 执行初始化脚本：`mysql -uroot -p < database/init_all.sql`
3. 将 Web 根目录指向 `public/`。
4. 按 `docs/部署文档.md` 配置 Nginx、PHP-FPM、Cron 与权限。

## 图片说明
项目中的设计图位统一引用 `.webp` 文件；当前仓库已在对应位置提供 `.webp.txt` 提示词文件，后续只需按提示词生成同名图片即可。
