-- database/schema.sql

-- Users table
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'user',
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    avatar VARCHAR(255),
    bio TEXT,
    last_login TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Countries table - Bảng quốc gia
CREATE TABLE countries (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    flag_image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Stories table - Đổi tên từ novels để phù hợp với bối cảnh
CREATE TABLE stories (
    id SERIAL PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(200) NOT NULL UNIQUE,
    description TEXT,
    cover_image VARCHAR(255),
    author_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    status VARCHAR(20) NOT NULL DEFAULT 'draft',
    views INTEGER DEFAULT 0,
    rating DECIMAL(3,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    type VARCHAR(50), -- Loại truyện (phim lẻ, phim bộ, review)
    country_id INTEGER REFERENCES countries(id) ON DELETE SET NULL, -- Quốc gia
    year INTEGER, -- Năm phát hành
    is_featured BOOLEAN DEFAULT FALSE, -- Nổi bật
    is_hot BOOLEAN DEFAULT FALSE, -- Hot
    is_completed BOOLEAN DEFAULT FALSE -- Hoàn thành
);

-- Categories table
CREATE TABLE categories (
    id SERIAL PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    slug VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    parent_id INTEGER REFERENCES categories(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Story categories relationship
CREATE TABLE story_categories (
    story_id INTEGER REFERENCES stories(id) ON DELETE CASCADE,
    category_id INTEGER REFERENCES categories(id) ON DELETE CASCADE,
    PRIMARY KEY (story_id, category_id)
);

-- Chapters table
CREATE TABLE chapters (
    id SERIAL PRIMARY KEY,
    story_id INTEGER REFERENCES stories(id) ON DELETE CASCADE,
    chapter_number INTEGER NOT NULL,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    views INTEGER DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (story_id, chapter_number)
);

-- Bookmarks table
CREATE TABLE bookmarks (
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    story_id INTEGER REFERENCES stories(id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, story_id)
);

-- Reading progress table
CREATE TABLE reading_progress (
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    story_id INTEGER REFERENCES stories(id) ON DELETE CASCADE,
    chapter_id INTEGER REFERENCES chapters(id) ON DELETE CASCADE,
    last_read TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, story_id)
);

-- Ratings table
CREATE TABLE ratings (
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    story_id INTEGER REFERENCES stories(id) ON DELETE CASCADE,
    rating INTEGER NOT NULL CHECK (rating >= 1 AND rating <= 5),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, story_id)
);

-- Comments table
CREATE TABLE comments (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE SET NULL, -- Cho phép bình luận không cần đăng nhập (NULL)
    story_id INTEGER REFERENCES stories(id) ON DELETE CASCADE,
    chapter_id INTEGER REFERENCES chapters(id) ON DELETE CASCADE,
    content TEXT NOT NULL,
    guest_name VARCHAR(100), -- Tên người bình luận khách
    guest_email VARCHAR(100), -- Email người bình luận khách
    parent_id INTEGER REFERENCES comments(id) ON DELETE CASCADE, -- ID bình luận cha (nếu là reply)
    is_admin_reply BOOLEAN DEFAULT FALSE, -- Đánh dấu nếu là reply từ admin
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- User activities table
CREATE TABLE activities (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    action VARCHAR(100) NOT NULL,
    details JSONB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Site settings table - Thêm bảng cài đặt cho trang web
CREATE TABLE settings (
    id VARCHAR(50) PRIMARY KEY,
    value TEXT NOT NULL,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Media table - Quản lý tệp đa phương tiện
CREATE TABLE media (
    id SERIAL PRIMARY KEY,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_type VARCHAR(50) NOT NULL,
    file_size INTEGER NOT NULL,
    user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Backups table - Quản lý các bản sao lưu
CREATE TABLE backups (
    id SERIAL PRIMARY KEY,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_size INTEGER NOT NULL,
    description TEXT,
    user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- System logs table - Nhật ký hệ thống
CREATE TABLE logs (
    id SERIAL PRIMARY KEY,
    level VARCHAR(20) NOT NULL,
    message TEXT NOT NULL,
    context JSONB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create indexes for better performance
CREATE INDEX idx_stories_slug ON stories(slug);
CREATE INDEX idx_stories_author ON stories(author_id);
CREATE INDEX idx_stories_country ON stories(country_id);
CREATE INDEX idx_chapters_story ON chapters(story_id);
CREATE INDEX idx_categories_slug ON categories(slug);
CREATE INDEX idx_countries_slug ON countries(slug);
CREATE INDEX idx_reading_progress_user ON reading_progress(user_id);
CREATE INDEX idx_comments_story ON comments(story_id);
CREATE INDEX idx_comments_chapter ON comments(chapter_id);
CREATE INDEX idx_comments_parent ON comments(parent_id); -- New index for parent_id
CREATE INDEX idx_activities_user ON activities(user_id);

-- Create trigger to update updated_at timestamp
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

CREATE TRIGGER update_stories_updated_at
    BEFORE UPDATE ON stories
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_chapters_updated_at
    BEFORE UPDATE ON chapters
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_users_updated_at
    BEFORE UPDATE ON users
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_countries_updated_at
    BEFORE UPDATE ON countries
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at_column();

-- Insert default admin user (password: admin123)
INSERT INTO users (username, email, password, role, status)
VALUES ('admin', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active');

-- Insert some default countries
INSERT INTO countries (name, slug, description)
VALUES
    ('Việt Nam', 'viet-nam', 'Phim Việt Nam'),
    ('Hàn Quốc', 'han-quoc', 'Phim Hàn Quốc'),
    ('Trung Quốc', 'trung-quoc', 'Phim Trung Quốc'),
    ('Mỹ', 'my', 'Phim Mỹ'),
    ('Nhật Bản', 'nhat-ban', 'Phim Nhật Bản'),
    ('Thái Lan', 'thai-lan', 'Phim Thái Lan'),
    ('Ấn Độ', 'an-do', 'Phim Ấn Độ'),
    ('Anh', 'anh', 'Phim Anh'),
    ('Pháp', 'phap', 'Phim Pháp'),
    ('Đài Loan', 'dai-loan', 'Phim Đài Loan');

-- Insert some default categories
INSERT INTO categories (name, slug, description)
VALUES
    ('Hành Động', 'hanh-dong', 'Phim hành động'),
    ('Võ Thuật', 'vo-thuat', 'Phim võ thuật'),
    ('Tình Cảm', 'tinh-cam', 'Phim tình cảm'),
    ('Hoạt Hình', 'hoat-hinh', 'Phim hoạt hình'),
    ('Viễn Tưởng', 'vien-tuong', 'Phim viễn tưởng'),
    ('Khoa Học', 'khoa-hoc', 'Phim khoa học'),
    ('Phiêu Lưu', 'phieu-luu', 'Phim phiêu lưu'),
    ('Hài Hước', 'hai-huoc', 'Phim hài hước'),
    ('Kinh Dị', 'kinh-di', 'Phim kinh dị'),
    ('Tâm Lý', 'tam-ly', 'Phim tâm lý'),
    ('Chiến Tranh', 'chien-tranh', 'Phim chiến tranh'),
    ('Cổ Trang', 'co-trang', 'Phim cổ trang'),
    ('Hình Sự', 'hinh-su', 'Phim hình sự');

-- Insert default settings
INSERT INTO settings (id, value, description)
VALUES
    ('site_name', 'Web Review Phim', 'Tên trang web'),
    ('site_description', 'Trang web review phim hay, cập nhật thông tin phim mới nhất', 'Mô tả trang web'),
    ('site_keywords', 'phim, review phim, phim hay, phim mới', 'Từ khóa trang web'),
    ('site_logo', '/assets/images/logo.png', 'Logo trang web'),
    ('site_favicon', '/assets/images/favicon.ico', 'Favicon trang web'),
    ('site_header', '<h1>Web Review Phim</h1>', 'Tiêu đề header mặc định'),
    ('site_footer', '© 2025 Web Review Phim - Tất cả quyền được bảo lưu', 'Nội dung footer mặc định'),
    ('story_description_template', 'Phim [title] [year] - [country] thuộc thể loại [categories]. Phim do [author] đánh giá.', 'Mẫu mô tả mặc định cho truyện'),
    ('max_featured_stories', '6', 'Số lượng truyện nổi bật hiển thị trên trang chủ'),
    ('max_latest_stories', '12', 'Số lượng truyện mới nhất hiển thị trên trang chủ'),
    ('max_popular_stories', '5', 'Số lượng truyện phổ biến hiển thị trên trang chủ'),
    ('max_completed_stories', '6', 'Số lượng truyện đã hoàn thành hiển thị trên trang chủ'),
    ('smtp_host', '', 'SMTP host'),
    ('smtp_port', '587', 'SMTP port'),
    ('smtp_user', '', 'SMTP username'),
    ('smtp_pass', '', 'SMTP password'),
    ('smtp_from', '', 'SMTP from email'),
    ('maintenance_mode', 'off', 'Chế độ bảo trì'),
    ('register_enabled', 'off', 'Cho phép đăng ký tài khoản mới'),
    ('comments_enabled', 'on', 'Cho phép bình luận'),
    ('guest_comments_enabled', 'on', 'Cho phép bình luận không cần đăng nhập'),
    ('analytics_code', '', 'Mã Google Analytics'),
    ('recaptcha_site_key', '', 'Google reCAPTCHA site key'),
    ('recaptcha_secret_key', '', 'Google reCAPTCHA secret key'),
    ('social_facebook', '', 'Đường dẫn Facebook'),
    ('social_twitter', '', 'Đường dẫn Twitter'),
    ('social_youtube', '', 'Đường dẫn YouTube'),
    ('social_instagram', '', 'Đường dẫn Instagram');
