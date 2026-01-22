# 使用官方 PHP 映像
FROM php:8.2-cli

# 安裝 PDO MySQL 擴展
RUN docker-php-ext-install pdo pdo_mysql

# 設置工作目錄
WORKDIR /app

# 複製所有文件到容器
COPY . .

# 暴露端口（Render 使用 10000）
EXPOSE 10000

# 啟動 PHP 內建伺服器
# Render 會自動設置 PORT 環境變數為 10000
CMD php -S 0.0.0.0:${PORT:-10000} -t public
