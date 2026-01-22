# 使用官方 PHP 映像
FROM php:8.2-cli

# 安裝 PDO MySQL 擴展
RUN docker-php-ext-install pdo pdo_mysql

# 設置工作目錄
WORKDIR /app

# 複製所有文件到容器
COPY . .

# 暴露端口
EXPOSE 10000

# 使用 router.php 啟動 PHP 內建伺服器
CMD php -S 0.0.0.0:${PORT:-10000} router.php
