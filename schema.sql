-- Efed CMS Database Schema
-- Compatible with MySQL 8+ and MariaDB 10.5+

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Create database if not exists
-- CREATE DATABASE IF NOT EXISTS efed_cms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE efed_cms;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role TINYINT UNSIGNED NOT NULL DEFAULT 1,
    twofa_secret VARCHAR(32) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role (role)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Companies table
CREATE TABLE IF NOT EXISTS companies (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    active BOOLEAN NOT NULL DEFAULT true,
    logo_url TEXT NULL,
    banner_url TEXT NULL,
    links JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_slug (slug),
    INDEX idx_active (active),
    INDEX idx_name (name)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Divisions table
CREATE TABLE IF NOT EXISTS divisions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    active BOOLEAN NOT NULL DEFAULT true,
    eligibility JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_slug (slug),
    INDEX idx_active (active),
    INDEX idx_name (name)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Wrestlers table
CREATE TABLE IF NOT EXISTS wrestlers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    active BOOLEAN NOT NULL DEFAULT true,
    record_wins INT UNSIGNED NOT NULL DEFAULT 0,
    record_losses INT UNSIGNED NOT NULL DEFAULT 0,
    record_draws INT UNSIGNED NOT NULL DEFAULT 0,
    elo INT NOT NULL DEFAULT 1200,
    points INT NOT NULL DEFAULT 0,
    profile_img_url TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_slug (slug),
    INDEX idx_active (active),
    INDEX idx_name (name),
    INDEX idx_elo (elo),
    INDEX idx_points (points)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Events table
CREATE TABLE IF NOT EXISTS events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(100) NOT NULL UNIQUE,
    company_id INT UNSIGNED NOT NULL,
    date DATE NOT NULL,
    name VARCHAR(255) NOT NULL,
    type VARCHAR(100) NOT NULL DEFAULT 'event',
    venue VARCHAR(255) NULL,
    attendance INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    INDEX idx_slug (slug),
    INDEX idx_company_id (company_id),
    INDEX idx_date (date),
    INDEX idx_type (type),
    INDEX idx_name (name)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Matches table
CREATE TABLE IF NOT EXISTS matches (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(100) NOT NULL UNIQUE,
    event_id INT UNSIGNED NOT NULL,
    division_id INT UNSIGNED NULL,
    company_id INT UNSIGNED NOT NULL,
    wrestler1_id INT UNSIGNED NOT NULL,
    wrestler2_id INT UNSIGNED NOT NULL,
    is_championship BOOLEAN NOT NULL DEFAULT false,
    championship_id INT UNSIGNED NULL,
    result_outcome ENUM('win', 'loss', 'draw', 'no_contest') NULL,
    result_method VARCHAR(100) NULL,
    result_round TINYINT UNSIGNED NULL,
    judges JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (division_id) REFERENCES divisions(id) ON DELETE SET NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (wrestler1_id) REFERENCES wrestlers(id) ON DELETE CASCADE,
    FOREIGN KEY (wrestler2_id) REFERENCES wrestlers(id) ON DELETE CASCADE,
    INDEX idx_slug (slug),
    INDEX idx_event_id (event_id),
    INDEX idx_division_id (division_id),
    INDEX idx_company_id (company_id),
    INDEX idx_wrestler1_id (wrestler1_id),
    INDEX idx_wrestler2_id (wrestler2_id),
    INDEX idx_is_championship (is_championship),
    INDEX idx_result_outcome (result_outcome)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Tags table
CREATE TABLE IF NOT EXISTS tags (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_slug (slug),
    INDEX idx_name (name)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Pivot tables for many-to-many relationships

-- Company-Division relationships (companies can have multiple divisions)
CREATE TABLE IF NOT EXISTS company_divisions (
    company_id INT UNSIGNED NOT NULL,
    division_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (company_id, division_id),
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (division_id) REFERENCES divisions(id) ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Wrestler-Company relationships 
CREATE TABLE IF NOT EXISTS wrestler_companies (
    wrestler_id INT UNSIGNED NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (wrestler_id, company_id),
    FOREIGN KEY (wrestler_id) REFERENCES wrestlers(id) ON DELETE CASCADE,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Wrestler-Division relationships
CREATE TABLE IF NOT EXISTS wrestler_divisions (
    wrestler_id INT UNSIGNED NOT NULL,
    division_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (wrestler_id, division_id),
    FOREIGN KEY (wrestler_id) REFERENCES wrestlers(id) ON DELETE CASCADE,
    FOREIGN KEY (division_id) REFERENCES divisions(id) ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Tag relationships
CREATE TABLE IF NOT EXISTS wrestler_tags (
    wrestler_id INT UNSIGNED NOT NULL,
    tag_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (wrestler_id, tag_id),
    FOREIGN KEY (wrestler_id) REFERENCES wrestlers(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS company_tags (
    company_id INT UNSIGNED NOT NULL,
    tag_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (company_id, tag_id),
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS division_tags (
    division_id INT UNSIGNED NOT NULL,
    tag_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (division_id, tag_id),
    FOREIGN KEY (division_id) REFERENCES divisions(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS event_tags (
    event_id INT UNSIGNED NOT NULL,
    tag_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (event_id, tag_id),
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS match_tags (
    match_id INT UNSIGNED NOT NULL,
    tag_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (match_id, tag_id),
    FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- Insert some sample data for testing

-- Sample companies
INSERT INTO companies (slug, name, active, logo_url, banner_url, links) VALUES 
('world-wrestling-entertainment', 'World Wrestling Entertainment', true, 'https://example.com/wwe-logo.png', 'https://example.com/wwe-banner.jpg', '{"website": "https://wwe.com", "twitter": "@WWE"}'),
('all-elite-wrestling', 'All Elite Wrestling', true, 'https://example.com/aew-logo.png', 'https://example.com/aew-banner.jpg', '{"website": "https://allelitewrestling.com", "twitter": "@AEW"}');

-- Sample divisions  
INSERT INTO divisions (slug, name, active, eligibility) VALUES
('heavyweight', 'Heavyweight Division', true, '{"min_weight": 200, "max_weight": null, "gender": "any"}'),
('cruiserweight', 'Cruiserweight Division', true, '{"min_weight": null, "max_weight": 205, "gender": "any"}'),
('womens', 'Women''s Division', true, '{"gender": "female"}');

-- Sample wrestlers
INSERT INTO wrestlers (slug, name, active, record_wins, record_losses, record_draws, elo, points, profile_img_url) VALUES
('john-cena', 'John Cena', true, 150, 45, 2, 1850, 2500, 'https://example.com/cena.jpg'),
('kenny-omega', 'Kenny Omega', true, 98, 32, 1, 1780, 2200, 'https://example.com/omega.jpg'),
('becky-lynch', 'Becky Lynch', true, 78, 28, 0, 1650, 1800, 'https://example.com/lynch.jpg');

-- Sample events
INSERT INTO events (slug, company_id, date, name, type, venue, attendance) VALUES
('wrestlemania-39', 1, '2023-04-01', 'WrestleMania 39', 'pay-per-view', 'SoFi Stadium', 67553),
('double-or-nothing-2023', 2, '2023-05-28', 'Double or Nothing 2023', 'pay-per-view', 'T-Mobile Arena', 8500);

-- Sample matches
INSERT INTO matches (slug, event_id, division_id, company_id, wrestler1_id, wrestler2_id, is_championship, result_outcome, result_method) VALUES
('cena-vs-omega-wm39', 1, 1, 1, 1, 2, false, 'win', 'pinfall'),
('lynch-title-defense', 2, 3, 2, 3, 2, true, 'win', 'submission');

-- Sample tags
INSERT INTO tags (slug, name) VALUES
('technical', 'Technical Wrestling'),
('high-flying', 'High Flying'),
('hardcore', 'Hardcore'),
('comedy', 'Comedy'),
('championship', 'Championship Match');

-- Sample relationships
INSERT INTO company_divisions (company_id, division_id) VALUES (1, 1), (1, 2), (1, 3), (2, 1), (2, 3);
INSERT INTO wrestler_companies (wrestler_id, company_id) VALUES (1, 1), (2, 2), (3, 2);
INSERT INTO wrestler_divisions (wrestler_id, division_id) VALUES (1, 1), (2, 1), (3, 3);
INSERT INTO wrestler_tags (wrestler_id, tag_id) VALUES (1, 1), (2, 1), (2, 2), (3, 1);
INSERT INTO match_tags (match_id, tag_id) VALUES (1, 1), (2, 5);