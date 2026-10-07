CREATE DATABASE IF NOT EXISTS review_system DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE review_system;

-- Admin Users
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Properties Configuration
CREATE TABLE IF NOT EXISTS properties (
    id INT AUTO_INCREMENT PRIMARY KEY,
    property_name VARCHAR(150) NOT NULL,
    notification_email VARCHAR(150) NOT NULL,
    custom_wording TEXT NULL,
    link_google VARCHAR(255) NULL,
    link_yelp VARCHAR(255) NULL,
    link_apartments_com VARCHAR(255) NULL,
    link_apartmentratings VARCHAR(255) NULL,
    link_other VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Captured Reviews (1-3 Stars)
CREATE TABLE IF NOT EXISTS captured_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    property_id INT NOT NULL,
    star_rating TINYINT NOT NULL,
    reviewer_name VARCHAR(100) NOT NULL,
    reviewer_email VARCHAR(150) NOT NULL,
    reviewer_phone VARCHAR(50) NULL,
    feedback_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_reviews_property (property_id),
    INDEX idx_reviews_created (created_at),
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
