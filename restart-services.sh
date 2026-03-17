#!/usr/bin/env bash

set -euo pipefail

PROJECT_DIR="/www/mall-ecommerce"
SERVICES=("php-fpm" "nginx" "redis")

echo "[1/4] 进入项目目录: ${PROJECT_DIR}"
cd "${PROJECT_DIR}"

echo "[2/4] 检查 Nginx 配置"
nginx -t

echo "[3/4] 依次重启服务"
for service in "${SERVICES[@]}"; do
    echo "正在重启 ${service} ..."
    systemctl restart "${service}"
done

echo "[4/4] 输出服务状态"
for service in "${SERVICES[@]}"; do
    status="$(systemctl is-active "${service}")"
    echo "${service}: ${status}"
done

echo "完成。可继续访问以下地址进行测试："
echo "- 导航页: https://magic.lyccc.xyz/"
echo "- 商城首页: https://magic.lyccc.xyz/mall"
