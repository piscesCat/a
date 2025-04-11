# Khai-Pro - Hệ thống quản lý nội dung

Khai-Pro là một hệ thống quản lý nội dung (CMS) được xây dựng trên CodeIgniter 4, cho phép người dùng tạo và quản lý các bài viết, hình ảnh và nội dung. Hệ thống bao gồm cả tính năng quản trị và giao diện người dùng.

## Tính năng chính

- Quản lý bài viết và phân loại
- Quản lý người dùng và phân quyền
- Tải lên hình ảnh (hỗ trợ lưu trữ nội bộ và Imgur)
- Tìm kiếm, phân trang, sắp xếp
- Hệ thống bình luận
- Thống kê và báo cáo
- Giao diện người dùng thân thiện

## Yêu cầu hệ thống

- PHP 7.4 hoặc cao hơn
- MySQL 5.7+ hoặc MariaDB 10.2+
- Composer
- Apache hoặc Nginx
- PHP extensions: intl, json, mbstring, mysqlnd, xml, curl

## Hướng dẫn cài đặt

### 1. Chuẩn bị môi trường

Đảm bảo bạn đã cài đặt PHP, MySQL/MariaDB và Composer trên máy chủ của bạn. Để kiểm tra, hãy chạy các lệnh sau:

```
php -v
mysql --version
composer --version
```

### 2. Tải mã nguồn

Clone repository hoặc tải về từ GitHub:

```
git clone https://github.com/username/khai-pro.git
cd khai-pro
```

### 3. Cài đặt các dependencies

Sử dụng Composer để cài đặt các thư viện cần thiết:

```
composer install
```

### 4. Cấu hình cơ sở dữ liệu

Tạo một cơ sở dữ liệu mới trong MySQL/MariaDB:

```
mysql -u root -p
CREATE DATABASE khai_pro CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON khai_pro.* TO 'your_username'@'localhost' IDENTIFIED BY 'your_password';
FLUSH PRIVILEGES;
EXIT;
```

### 5. Cấu hình ứng dụng

Sao chép file `.env.example` thành `.env` và cập nhật thông tin cơ sở dữ liệu:

```
cp .env.example .env
```

Mở file `.env` và sửa các thông tin sau:

```
database.default.hostname = localhost
database.default.database = khai_pro
database.default.username = your_username
database.default.password = your_password
database.default.DBDriver = MySQLi
```

### 6. Cài đặt cơ sở dữ liệu

Có hai cách để cài đặt cơ sở dữ liệu:

#### Cách 1: Sử dụng giao diện web

- Truy cập trang web của bạn (ví dụ: `http://your-domain.com/install`)
- Làm theo hướng dẫn cài đặt trên trang web

#### Cách 2: Sử dụng file SQL

- Import file SQL trong thư mục `database`:

```
mysql -u your_username -p khai_pro < database/schema.sql
```

### 7. Cấu hình web server

#### Cho Apache:

Tạo file `.htaccess` trong thư mục root với nội dung:

```
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php/$1 [L]
```

#### Cho Nginx:

```
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/khai-pro/public;

    index index.php index.html index.htm;

    location / {
        try_files $uri $uri/ /index.php$is_args$args;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

### 8. Phân quyền thư mục

Đảm bảo các thư mục sau có quyền ghi:

```
sudo chmod -R 755 writable/
sudo chmod -R 755 public/uploads/
```

### 9. Truy cập trang quản trị

Sau khi cài đặt, bạn có thể truy cập trang quản trị tại:

```
http://your-domain.com/admin
```

Thông tin đăng nhập mặc định:
- Email: admin@example.com
- Mật khẩu: admin123

**Lưu ý quan trọng**: Hãy đổi mật khẩu mặc định ngay sau khi đăng nhập lần đầu.

## Cấu hình tính năng tải lên Imgur

Hệ thống hỗ trợ tải lên hình ảnh lên Imgur song song với lưu trữ nội bộ. Để cấu hình:

1. Tạo tài khoản Imgur và đăng ký ứng dụng tại: https://api.imgur.com/oauth2/addclient
2. Chọn "OAuth 2 authorization without a callback URL"
3. Lấy Client ID từ Imgur
4. Trong trang quản trị, vào "Quản lý tải lên" > "Cài đặt"
5. Kích hoạt tính năng tải lên Imgur và nhập Client ID

## Cấu trúc thư mục

```
khai-pro/
├── app/                    # Mã nguồn chính
│   ├── Config/             # Cấu hình ứng dụng
│   ├── Controllers/        # Các controllers
│   ├── Models/             # Các models
│   └── Views/              # Các templates
├── database/               # Cấu trúc và dữ liệu mẫu
├── public/                 # Thư mục public
│   ├── assets/             # CSS, JS, images
│   └── uploads/            # Thư mục lưu trữ file tải lên
└── writable/               # Thư mục cần quyền ghi (logs, cache)
```

## Các vấn đề thường gặp

### Lỗi 404 khi truy cập trang

- Đảm bảo đã cấu hình đúng file `.htaccess` hoặc cấu hình Nginx
- Kiểm tra quyền truy cập của thư mục public

### Lỗi khi tải lên tệp

- Kiểm tra quyền ghi của thư mục `public/uploads/`
- Đảm bảo đã cài đặt PHP extension `fileinfo`

### Lỗi kết nối cơ sở dữ liệu

- Kiểm tra thông tin kết nối trong file `.env`
- Đảm bảo cơ sở dữ liệu đã được tạo và người dùng có quyền truy cập

## Đóng góp và phát triển

Mọi đóng góp cho dự án đều được hoan nghênh. Vui lòng tạo pull request hoặc báo cáo lỗi qua mục Issues của GitHub.

## Giấy phép

Dự án này được phân phối dưới giấy phép MIT. Xem file `LICENSE` để biết thêm chi tiết.
# not
# x
# x
# x
# x
# a
