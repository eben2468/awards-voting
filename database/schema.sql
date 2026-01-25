-- Awards Voting System Database Schema
-- MySQL Database Script

-- Create Database
CREATE DATABASE IF NOT EXISTS awards_voting CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE awards_voting;

-- ========================================
-- 1. USERS TABLE
-- ========================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fullname VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(20) DEFAULT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') DEFAULT 'user',
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    avatar VARCHAR(255) DEFAULT NULL,
    email_verified_at TIMESTAMP NULL,
    remember_token VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- 2. EVENTS TABLE
-- ========================================
CREATE TABLE IF NOT EXISTS events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(220) NOT NULL UNIQUE,
    description TEXT,
    banner VARCHAR(255) DEFAULT NULL,
    start_date DATETIME NOT NULL,
    end_date DATETIME NOT NULL,
    status ENUM('draft', 'active', 'completed', 'cancelled') DEFAULT 'draft',
    voting_type ENUM('free', 'paid') DEFAULT 'free',
    vote_price DECIMAL(10, 2) DEFAULT 0.00,
    is_featured BOOLEAN DEFAULT FALSE,
    total_votes INT DEFAULT 0,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_slug (slug),
    INDEX idx_dates (start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- 3. CATEGORIES TABLE
-- ========================================
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(170) NOT NULL,
    description TEXT,
    icon VARCHAR(100) DEFAULT NULL,
    display_order INT DEFAULT 0,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    UNIQUE KEY unique_category_event (event_id, slug),
    INDEX idx_event (event_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- 4. NOMINEES TABLE
-- ========================================
CREATE TABLE IF NOT EXISTS nominees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(170) NOT NULL,
    photo VARCHAR(255) DEFAULT NULL,
    description TEXT,
    shortcode VARCHAR(20) NOT NULL UNIQUE,
    social_links JSON DEFAULT NULL,
    vote_count INT DEFAULT 0,
    status ENUM('active', 'inactive', 'disqualified') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    INDEX idx_category (category_id),
    INDEX idx_shortcode (shortcode),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- 5. VOTES TABLE
-- ========================================
CREATE TABLE IF NOT EXISTS votes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    nominee_id INT NOT NULL,
    category_id INT NOT NULL,
    event_id INT NOT NULL,
    vote_count INT DEFAULT 1,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (nominee_id) REFERENCES nominees(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    -- UNIQUE KEY unique_vote (user_id, category_id), -- Removed for unlimited voting
    INDEX idx_nominee (nominee_id),
    INDEX idx_event (event_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- 6. TRANSACTIONS TABLE
-- ========================================
CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    event_id INT DEFAULT NULL,
    nominee_id INT DEFAULT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    currency VARCHAR(10) DEFAULT 'GHS',
    reference VARCHAR(100) NOT NULL UNIQUE,
    payment_method ENUM('momo', 'card', 'ussd', 'bank') DEFAULT 'momo',
    provider VARCHAR(50) DEFAULT NULL,
    status ENUM('pending', 'success', 'failed', 'refunded') DEFAULT 'pending',
    metadata JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE SET NULL,
    FOREIGN KEY (nominee_id) REFERENCES nominees(id) ON DELETE SET NULL,
    INDEX idx_reference (reference),
    INDEX idx_status (status),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- 7. AUDIT LOGS TABLE
-- ========================================
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(50) DEFAULT NULL,
    record_id INT DEFAULT NULL,
    old_values JSON DEFAULT NULL,
    new_values JSON DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_action (action),
    INDEX idx_user (user_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- 8. NOTIFICATIONS TABLE
-- ========================================
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'danger') DEFAULT 'info',
    is_read BOOLEAN DEFAULT FALSE,
    link VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_read (is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- 9. PASSWORD RESETS TABLE
-- ========================================
CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- INSERT DEFAULT ADMIN USER
-- ========================================
-- Password: Admin@123 (hashed)
INSERT INTO users (fullname, email, phone, password, role, status) VALUES
('System Administrator', 'votehub99@gmail.com', '+233000000000', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active');

-- ========================================
-- INSERT SAMPLE DATA
-- ========================================
-- Sample Event
INSERT INTO events (title, slug, description, start_date, end_date, status, is_featured, created_by) VALUES
('Ghana Music Awards 2025', 'ghana-music-awards-2025', 'The prestigious annual celebration of Ghanaian musical excellence. Vote for your favorite artists across multiple categories.', '2025-01-01 00:00:00', '2025-12-31 23:59:59', 'active', TRUE, 1),
('Youth Excellence Awards', 'youth-excellence-awards', 'Celebrating young achievers making a difference in their communities. Nominate and vote for outstanding youth leaders.', '2025-02-01 00:00:00', '2025-06-30 23:59:59', 'active', TRUE, 1),
('Tech Innovation Awards', 'tech-innovation-awards', 'Recognizing groundbreaking technology innovations and the brilliant minds behind them.', '2025-03-01 00:00:00', '2025-09-30 23:59:59', 'active', FALSE, 1);

-- Sample Categories for Ghana Music Awards
INSERT INTO categories (event_id, name, slug, description, display_order) VALUES
(1, 'Artist of the Year', 'artist-of-the-year', 'The most outstanding artist of the year', 1),
(1, 'Song of the Year', 'song-of-the-year', 'The best song released this year', 2),
(1, 'Album of the Year', 'album-of-the-year', 'The best album released this year', 3),
(1, 'Best New Artist', 'best-new-artist', 'Most promising new talent', 4),
(1, 'Best Collaboration', 'best-collaboration', 'Best musical collaboration', 5);

-- Sample Categories for Youth Excellence Awards
INSERT INTO categories (event_id, name, slug, description, display_order) VALUES
(2, 'Young Entrepreneur', 'young-entrepreneur', 'Outstanding young business leader', 1),
(2, 'Community Leader', 'community-leader', 'Youth making impact in their community', 2),
(2, 'Tech Innovator', 'tech-innovator', 'Young minds in technology', 3);

-- Sample Nominees
INSERT INTO nominees (category_id, name, slug, description, shortcode, vote_count) VALUES
(1, 'Kwame Mensah', 'kwame-mensah', 'Award-winning Afrobeats sensation', 'KM001', 150),
(1, 'Ama Serwaa', 'ama-serwaa', 'Queen of Highlife music', 'AS002', 230),
(1, 'DJ Spinall', 'dj-spinall', 'Master of the turntables', 'DS003', 180),
(2, 'Midnight Vibes - Kwame Mensah', 'midnight-vibes', 'Chart-topping hit single', 'MV001', 320),
(2, 'Sunshine - Ama Serwaa', 'sunshine', 'Feel-good summer anthem', 'SS002', 290),
(2, 'Unity - DJ Spinall ft. Various', 'unity', 'Powerful collaboration', 'UN003', 210),
(4, 'Young Kofi', 'young-kofi', 'Rising star in Afropop', 'YK001', 450),
(4, 'Princess Akua', 'princess-akua', 'Fresh voice in R&B', 'PA002', 380),
(6, 'Nana Yaw Tech', 'nana-yaw-tech', 'Founder of AgriTech Solutions', 'NY001', 125),
(6, 'Efua Startup', 'efua-startup', 'CEO of FinConnect', 'ES002', 98);
