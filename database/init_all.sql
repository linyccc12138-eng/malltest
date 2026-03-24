CREATE DATABASE IF NOT EXISTS membership_center CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS magic_mall CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE membership_center;

CREATE TABLE IF NOT EXISTS classes (
  fid INT AUTO_INCREMENT PRIMARY KEY,
  fname VARCHAR(255) DEFAULT NULL COMMENT '会员等级名称',
  foff DECIMAL(10,2) DEFAULT NULL COMMENT '会员折扣'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='会员等级表';

CREATE TABLE IF NOT EXISTS member (
  fid INT AUTO_INCREMENT PRIMARY KEY,
  fnumber VARCHAR(255) DEFAULT NULL COMMENT '会员编号',
  fname VARCHAR(255) DEFAULT NULL COMMENT '会员名称',
  fclassesid INT DEFAULT NULL COMMENT '会员等级ID',
  fclassesname VARCHAR(255) DEFAULT NULL COMMENT '会员等级名称',
  faccruedamount DECIMAL(10,2) DEFAULT '0.00' COMMENT '累计充值金额',
  fbalance DECIMAL(10,2) DEFAULT '0.00' COMMENT '余额',
  fmark VARCHAR(255) DEFAULT NULL COMMENT '备注',
  UNIQUE KEY idx_fnumber (fnumber),
  KEY idx_fname (fname)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='会员信息表';

CREATE TABLE IF NOT EXISTS menmberdetail (
  fid INT AUTO_INCREMENT PRIMARY KEY,
  fdate DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '日期',
  fmode VARCHAR(255) DEFAULT NULL COMMENT '操作类型',
  fmemberid INT DEFAULT NULL COMMENT '会员ID',
  fmembername VARCHAR(255) DEFAULT NULL COMMENT '会员名称',
  fclassesid INT DEFAULT NULL COMMENT '会员等级ID',
  fclassesname VARCHAR(255) DEFAULT NULL COMMENT '会员等级名称',
  fgoods VARCHAR(1024) DEFAULT NULL COMMENT '商品名称',
  famount DECIMAL(10,2) DEFAULT NULL COMMENT '调整金额',
  fbalance DECIMAL(10,2) DEFAULT NULL COMMENT '调整后余额',
  fmark VARCHAR(255) DEFAULT NULL COMMENT '备注',
  KEY idx_fmemberid (fmemberid),
  KEY idx_fdate (fdate)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='会员明细表';

CREATE TABLE IF NOT EXISTS menmberdetail_log (
  fid INT AUTO_INCREMENT PRIMARY KEY,
  fdate DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '日期',
  fmode VARCHAR(255) DEFAULT NULL COMMENT '操作类型',
  fmemberid INT DEFAULT NULL COMMENT '会员ID',
  fmembername VARCHAR(255) DEFAULT NULL COMMENT '会员名称',
  fclassesid INT DEFAULT NULL COMMENT '会员等级ID',
  fclassesname VARCHAR(255) DEFAULT NULL COMMENT '会员等级名称',
  fgoods VARCHAR(1024) DEFAULT NULL COMMENT '商品名称',
  famount DECIMAL(10,2) DEFAULT NULL COMMENT '调整金额',
  fbalance DECIMAL(10,2) DEFAULT NULL COMMENT '调整后余额',
  fmark VARCHAR(255) DEFAULT NULL COMMENT '备注',
  KEY idx_fmemberid (fmemberid),
  KEY idx_fdate (fdate)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='会员明细日志表';

INSERT INTO classes (fid, fname, foff) VALUES
(1, '普通会员', 1.00),
(2, '白银会员', 0.95),
(3, '黄金会员', 0.90),
(4, '星耀会员', 0.85)
ON DUPLICATE KEY UPDATE fname = VALUES(fname), foff = VALUES(foff);

INSERT INTO member (fid, fnumber, fname, fclassesid, fclassesname, faccruedamount, fbalance, fmark) VALUES
(1, 'M1001', '林一辰', 3, '黄金会员', 1688.00, 888.00, '商城演示会员'),
(2, 'M1002', '沈知夏', 2, '白银会员', 988.00, 366.00, '课程购买偏好'),
(3, 'M1003', '周观澜', 1, '普通会员', 0.00, 0.00, '未充值会员')
ON DUPLICATE KEY UPDATE fname = VALUES(fname), fclassesid = VALUES(fclassesid), fclassesname = VALUES(fclassesname), faccruedamount = VALUES(faccruedamount), fbalance = VALUES(fbalance), fmark = VALUES(fmark);

USE magic_mall;

CREATE TABLE IF NOT EXISTS mall_users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(80) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  nickname VARCHAR(120) NOT NULL,
  phone VARCHAR(32) DEFAULT '',
  role VARCHAR(20) NOT NULL DEFAULT 'customer',
  openid VARCHAR(128) DEFAULT NULL,
  membership_member_id INT DEFAULT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  last_login_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uniq_openid (openid),
  KEY idx_membership_member_id (membership_member_id),
  KEY idx_role_status (role, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商城用户表';

CREATE TABLE IF NOT EXISTS user_addresses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  receiver_name VARCHAR(100) NOT NULL,
  receiver_phone VARCHAR(32) NOT NULL,
  province VARCHAR(64) NOT NULL,
  city VARCHAR(64) NOT NULL,
  district VARCHAR(64) NOT NULL,
  detail_address VARCHAR(255) NOT NULL,
  postal_code VARCHAR(20) DEFAULT '',
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY idx_user_default (user_id, is_default)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='收货地址表';

CREATE TABLE IF NOT EXISTS categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  parent_id INT NOT NULL DEFAULT 0,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(160) NOT NULL,
  level INT NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  is_visible TINYINT(1) NOT NULL DEFAULT 1,
  type VARCHAR(20) NOT NULL DEFAULT 'product',
  icon_image VARCHAR(255) DEFAULT '',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uniq_category_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商品分类表';

CREATE TABLE IF NOT EXISTS products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  category_id INT NOT NULL,
  name VARCHAR(180) NOT NULL,
  slug VARCHAR(200) NOT NULL,
  summary VARCHAR(255) DEFAULT '',
  subtitle VARCHAR(255) DEFAULT '',
  brand VARCHAR(120) DEFAULT '',
  price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  market_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  rating DECIMAL(3,1) NOT NULL DEFAULT 4.8,
  sales_count INT NOT NULL DEFAULT 0,
  stock_total INT NOT NULL DEFAULT 0,
  is_on_sale TINYINT(1) NOT NULL DEFAULT 1,
  support_member_discount TINYINT(1) NOT NULL DEFAULT 1,
  is_course TINYINT(1) NOT NULL DEFAULT 0,
  is_recommended_course TINYINT(1) NOT NULL DEFAULT 0,
  is_new_arrival TINYINT(1) NOT NULL DEFAULT 0,
  quick_view_text TEXT,
  cover_image VARCHAR(255) DEFAULT '',
  gallery_json JSON DEFAULT NULL,
  detail_html MEDIUMTEXT,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uniq_product_slug (slug),
  KEY idx_product_category (category_id),
  KEY idx_product_flags (is_on_sale, is_course, is_new_arrival, is_recommended_course)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商品表';

CREATE TABLE IF NOT EXISTS product_skus (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  sku_code VARCHAR(120) NOT NULL,
  attribute_json JSON DEFAULT NULL,
  price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  stock INT NOT NULL DEFAULT 0,
  cover_image VARCHAR(255) DEFAULT '',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY idx_sku_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商品SKU表';

CREATE TABLE IF NOT EXISTS carts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uniq_cart_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='购物车表';

CREATE TABLE IF NOT EXISTS cart_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cart_id INT NOT NULL,
  product_id INT NOT NULL,
  sku_id INT NOT NULL,
  quantity INT NOT NULL DEFAULT 1,
  unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  member_discount_rate DECIMAL(10,2) NOT NULL DEFAULT 1.00,
  final_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  item_status VARCHAR(30) NOT NULL DEFAULT 'valid',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uniq_cart_product (cart_id, product_id, sku_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='购物车明细表';

CREATE TABLE IF NOT EXISTS orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_no VARCHAR(50) NOT NULL,
  user_id INT NOT NULL,
  address_snapshot_json JSON NOT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'pending_payment',
  payment_status VARCHAR(20) NOT NULL DEFAULT 'unpaid',
  payment_method VARCHAR(20) NOT NULL DEFAULT 'unselected',
  subtotal_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  payable_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  paid_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  member_discount_rate DECIMAL(10,2) NOT NULL DEFAULT 1.00,
  membership_snapshot_json JSON DEFAULT NULL,
  closed_reason VARCHAR(255) DEFAULT '',
  shipping_company VARCHAR(100) DEFAULT '',
  shipping_no VARCHAR(100) DEFAULT '',
  placed_at DATETIME DEFAULT NULL,
  paid_at DATETIME DEFAULT NULL,
  shipped_at DATETIME DEFAULT NULL,
  completed_at DATETIME DEFAULT NULL,
  closed_at DATETIME DEFAULT NULL,
  expires_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uniq_order_no (order_no),
  KEY idx_order_user_status (user_id, status),
  KEY idx_order_payment_status (payment_status, status),
  KEY idx_order_expire (status, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='订单表';

CREATE TABLE IF NOT EXISTS order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  product_id INT NOT NULL,
  sku_id INT NOT NULL,
  product_name VARCHAR(180) NOT NULL,
  sku_name VARCHAR(180) DEFAULT '',
  quantity INT NOT NULL DEFAULT 1,
  unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  final_unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  line_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  support_member_discount TINYINT(1) NOT NULL DEFAULT 1,
  cover_image VARCHAR(255) DEFAULT '',
  attribute_snapshot_json JSON DEFAULT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY idx_order_item_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='订单明细表';

CREATE TABLE IF NOT EXISTS payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  payment_no VARCHAR(50) NOT NULL,
  method VARCHAR(20) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  transaction_no VARCHAR(100) DEFAULT '',
  request_payload_json JSON DEFAULT NULL,
  response_payload_json JSON DEFAULT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uniq_payment_no (payment_no),
  KEY idx_payment_order (order_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='支付记录表';

CREATE TABLE IF NOT EXISTS wallet_ledger (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  order_id INT DEFAULT NULL,
  type VARCHAR(40) NOT NULL,
  amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  remark VARCHAR(255) DEFAULT '',
  created_at DATETIME NOT NULL,
  KEY idx_wallet_user (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商城钱包记录表';

CREATE TABLE IF NOT EXISTS activities (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(180) NOT NULL,
  summary VARCHAR(255) DEFAULT '',
  link_url VARCHAR(500) DEFAULT '',
  thumbnail_image VARCHAR(255) DEFAULT '',
  content_html MEDIUMTEXT,
  display_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  starts_at DATETIME DEFAULT NULL,
  ends_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='导航页热门活动表';

CREATE TABLE IF NOT EXISTS system_settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  setting_group VARCHAR(60) NOT NULL,
  setting_key VARCHAR(100) NOT NULL,
  setting_value TEXT,
  is_encrypted TINYINT(1) NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uniq_setting_group_key (setting_group, setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统设置表';

CREATE TABLE IF NOT EXISTS system_logs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  level VARCHAR(20) NOT NULL,
  channel VARCHAR(40) NOT NULL,
  message VARCHAR(255) NOT NULL,
  context_json JSON DEFAULT NULL,
  user_id INT DEFAULT NULL,
  ip_address VARCHAR(64) DEFAULT '',
  user_agent VARCHAR(255) DEFAULT '',
  created_at DATETIME NOT NULL,
  KEY idx_logs_level_channel (level, channel),
  KEY idx_logs_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='分级系统日志表';

CREATE TABLE IF NOT EXISTS auth_lockouts (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  lock_scope VARCHAR(20) NOT NULL COMMENT '锁定范围：user/ip',
  identifier VARCHAR(190) NOT NULL COMMENT '用户ID字符串或IP地址',
  user_id INT DEFAULT NULL COMMENT '关联用户ID',
  failed_attempts INT NOT NULL DEFAULT 0 COMMENT '累计失败次数',
  captcha_required TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否要求验证码',
  locked_until DATETIME DEFAULT NULL COMMENT '锁定截止时间',
  last_failed_at DATETIME DEFAULT NULL COMMENT '最后失败时间',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uniq_auth_lockout_scope_identifier (lock_scope, identifier),
  KEY idx_auth_lockout_locked_until (locked_until),
  KEY idx_auth_lockout_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='登录安全锁定表';

INSERT INTO mall_users (id, username, password_hash, nickname, phone, role, openid, membership_member_id, status, last_login_at, created_at, updated_at) VALUES
(1, 'lyccc', '$2y$10$iZp19nAOm0msC7W76dh00u/WqGW2dSGFuQ/WqghyUzEv.umgLtByS', 'lyccc', '13206335421', 'admin', NULL, 1, 'active', NULL, NOW(), NOW()),
(2, 'demo_user', '$2y$12$HI463k7fif.ZsDYr6S1XEOoW8yyh/G5Fkblaf57vyXLMjWUWQjk8K', '演示用户', '13900000000', 'customer', NULL, 1, 'active', NULL, NOW(), NOW())
ON DUPLICATE KEY UPDATE nickname = VALUES(nickname), phone = VALUES(phone), role = VALUES(role), membership_member_id = VALUES(membership_member_id), status = VALUES(status), updated_at = NOW();

INSERT INTO user_addresses (user_id, receiver_name, receiver_phone, province, city, district, detail_address, postal_code, is_default, created_at, updated_at)
SELECT 2, '演示用户', '13900000000', '浙江省', '杭州市', '西湖区', '灵隐街道花影路 88 号', '310000', 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM user_addresses WHERE user_id = 2);

INSERT INTO categories (id, parent_id, name, slug, level, sort_order, is_visible, type, icon_image, created_at, updated_at) VALUES
(1, 0, '美妆香氛', 'beauty', 1, 1, 1, 'product', '/assets/images/navigation/nav-beauty.webp', NOW(), NOW()),
(2, 0, '服饰配件', 'fashion', 1, 2, 1, 'product', '/assets/images/navigation/nav-fashion.webp', NOW(), NOW()),
(3, 0, '灵感课程', 'courses', 1, 3, 1, 'course', '/assets/images/navigation/nav-course.webp', NOW(), NOW())
ON DUPLICATE KEY UPDATE name = VALUES(name), sort_order = VALUES(sort_order), updated_at = NOW();

INSERT INTO products (id, category_id, name, slug, summary, subtitle, brand, price, market_price, rating, sales_count, stock_total, is_on_sale, support_member_discount, is_course, is_recommended_course, is_new_arrival, quick_view_text, cover_image, gallery_json, detail_html, created_at, updated_at) VALUES
(1, 1, '月桂鎏金花瓣香氛蜡烛', 'golden-laurel-candle', '穆夏花饰感的金粉花瓣与木质香调', '穆夏花饰感的金粉花瓣与木质香调', 'Arc Bloom', 268.00, 328.00, 4.9, 86, 24, 1, 1, 0, 0, 1, '适合客厅与书房的氛围型香氛蜡烛，支持会员折扣。', '/assets/images/products/candle-gold.webp', JSON_ARRAY('/assets/images/products/candle-gold.webp', '/assets/images/products/candle-detail.webp'), '<p>以月桂、雪松与白麝香构成层次感香气，适合营造沉静优雅的家居氛围。</p>', NOW(), NOW()),
(2, 2, '雾茶丝绒披肩', 'mist-tea-shawl', '柔雾茶绿与细致流苏，适合春秋叠搭', '柔雾茶绿与细致流苏，适合春秋叠搭', 'Velvet Muse', 199.00, 259.00, 4.8, 52, 30, 1, 1, 0, 0, 1, '披肩轻盈柔软，适合通勤与旅行。', '/assets/images/products/shawl-mist.webp', JSON_ARRAY('/assets/images/products/shawl-mist.webp'), '<p>披肩采用轻柔丝绒触感面料，兼顾轻保暖与装饰性。</p>', NOW(), NOW()),
(3, 3, '构图与叙事美学课', 'composition-story-course', '课程型商品，支持推荐课程展示', '课程型商品，支持推荐课程展示', 'Magic Studio', 399.00, 499.00, 5.0, 108, 999, 1, 0, 1, 1, 0, '从构图、节奏到页面叙事的完整创作课。', '/assets/images/products/course-composition.webp', JSON_ARRAY('/assets/images/products/course-composition.webp'), '<p>课程覆盖视觉叙事、配色节奏、页面组织方式等系统内容。</p>', NOW(), NOW()),
(4, 1, '琥珀花窗身体乳', 'amber-window-lotion', '温暖琥珀与奶霜触感，适合秋冬护理', '温暖琥珀与奶霜触感，适合秋冬护理', 'Arc Bloom', 158.00, 198.00, 4.7, 41, 18, 1, 1, 0, 0, 0, '柔润不黏腻，适合夜间香氛护理。', '/assets/images/products/lotion-amber.webp', JSON_ARRAY('/assets/images/products/lotion-amber.webp'), '<p>身体乳主打滋润与香气留存，适合搭配同系列香氛使用。</p>', NOW(), NOW())
ON DUPLICATE KEY UPDATE summary = VALUES(summary), subtitle = VALUES(subtitle), brand = VALUES(brand), price = VALUES(price), stock_total = VALUES(stock_total), updated_at = NOW();

INSERT INTO product_skus (product_id, sku_code, attribute_json, price, stock, cover_image, created_at, updated_at) VALUES
(1, 'SKU-CANDLE-01', JSON_OBJECT('香型', '月桂木质调', '规格', '220g'), 268.00, 24, '/assets/images/products/candle-gold.webp', NOW(), NOW()),
(2, 'SKU-SHAWL-01', JSON_OBJECT('颜色', '雾茶绿', '尺码', '均码'), 199.00, 30, '/assets/images/products/shawl-mist.webp', NOW(), NOW()),
(3, 'SKU-COURSE-01', JSON_OBJECT('版本', '标准版'), 399.00, 999, '/assets/images/products/course-composition.webp', NOW(), NOW()),
(4, 'SKU-LOTION-01', JSON_OBJECT('香型', '琥珀花窗', '规格', '250ml'), 158.00, 18, '/assets/images/products/lotion-amber.webp', NOW(), NOW());

INSERT INTO activities (title, summary, link_url, thumbnail_image, content_html, display_order, is_active, starts_at, ends_at, created_at, updated_at)
SELECT '春季花影会员礼遇', '会员绑定后购买指定商品可享折扣与活动推荐。', '', '/assets/images/navigation/activity-spring.webp', '<p>管理员可在后台继续编辑活动富文本内容与展示顺序。</p>', 1, 1, NOW(), NULL, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM activities WHERE title = '春季花影会员礼遇');

INSERT INTO system_settings (setting_group, setting_key, setting_value, is_encrypted, updated_at) VALUES
('login_security', 'max_failed_attempts', '10', 0, NOW()),
('login_security', 'lock_minutes', '60', 0, NOW()),
('captcha', 'trigger_failed_attempts', '3', 0, NOW()),
('log', 'min_level', 'info', 0, NOW()),
('log', 'retention_days', '30', 0, NOW()),
('log', 'max_size_mb', '10', 0, NOW()),
('notifications', 'admin_paid_enabled', '1', 0, NOW()),
('notifications', 'admin_cancelled_enabled', '1', 0, NOW()),
('notifications', 'user_created_enabled', '1', 0, NOW()),
('notifications', 'user_paid_enabled', '1', 0, NOW()),
('notifications', 'user_shipped_enabled', '1', 0, NOW()),
('notifications', 'user_completed_enabled', '1', 0, NOW()),
('notifications', 'user_closed_enabled', '1', 0, NOW())
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW();
