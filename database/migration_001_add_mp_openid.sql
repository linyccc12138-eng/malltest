-- Migration: Add mp_openid column for Mini Program WeChat binding support
-- Run this on the magic_mall database

ALTER TABLE mall_users
  ADD COLUMN mp_openid VARCHAR(128) DEFAULT NULL AFTER openid,
  ADD UNIQUE KEY uniq_mp_openid (mp_openid);

-- Update the init_all.sql to include this column for fresh installs
