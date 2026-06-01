-- AutoThreads Database Schema
-- MySQL 8.0+
-- Run this file to create the complete database structure

CREATE DATABASE IF NOT EXISTS autothreads
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE autothreads;

-- ============================================================
-- USERS TABLE
-- Supports multi-tenant SaaS with role-based access
-- ============================================================
CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'user', 'moderator') DEFAULT 'user',
    plan ENUM('free', 'starter', 'pro', 'enterprise') DEFAULT 'free',
    is_active TINYINT(1) DEFAULT 1,
    email_verified_at TIMESTAMP NULL,
    last_login_at TIMESTAMP NULL,
    settings JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_email (email),
    INDEX idx_users_role (role),
    INDEX idx_users_plan (plan)
) ENGINE=InnoDB;

-- ============================================================
-- THREADS ACCOUNTS
-- Users can connect multiple Threads accounts
-- ============================================================
CREATE TABLE threads_accounts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    threads_user_id VARCHAR(100) NOT NULL,
    username VARCHAR(100) NOT NULL,
    access_token TEXT NOT NULL,
    refresh_token TEXT NULL,
    token_expires_at TIMESTAMP NULL,
    is_active TINYINT(1) DEFAULT 1,
    metadata JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_threads_accounts_user (user_id),
    UNIQUE INDEX idx_threads_user_unique (threads_user_id)
) ENGINE=InnoDB;

-- ============================================================
-- NICHES
-- Content categories that users can manage
-- ============================================================
CREATE TABLE niches (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    description TEXT NULL,
    keywords JSON DEFAULT NULL,
    target_audience VARCHAR(255) NULL,
    is_active TINYINT(1) DEFAULT 1,
    post_count INT UNSIGNED DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_niches_user (user_id),
    UNIQUE INDEX idx_niches_user_slug (user_id, slug)
) ENGINE=InnoDB;

-- ============================================================
-- TOPICS
-- Generated content ideas stored for reuse
-- ============================================================
CREATE TABLE topics (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    niche_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    category ENUM(
        'story', 'product_recommendation', 'comparison',
        'productivity_tip', 'viral_hook', 'opinion',
        'list_post', 'wish_i_knew', 'general'
    ) DEFAULT 'general',
    status ENUM('new', 'used', 'archived', 'rejected') DEFAULT 'new',
    priority TINYINT UNSIGNED DEFAULT 5,
    metadata JSON DEFAULT NULL,
    used_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (niche_id) REFERENCES niches(id) ON DELETE CASCADE,
    INDEX idx_topics_user_status (user_id, status),
    INDEX idx_topics_niche (niche_id),
    INDEX idx_topics_category (category),
    FULLTEXT INDEX idx_topics_title_ft (title)
) ENGINE=InnoDB;

-- ============================================================
-- AFFILIATE LINKS
-- Product links with tracking and CTA management
-- ============================================================
CREATE TABLE affiliate_links (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    niche_id BIGINT UNSIGNED NULL,
    product_name VARCHAR(255) NOT NULL,
    url TEXT NOT NULL,
    short_url VARCHAR(500) NULL,
    cta_style ENUM('soft', 'direct', 'curiosity', 'urgency', 'social_proof') DEFAULT 'soft',
    campaign_tag VARCHAR(100) NULL,
    tracking_params JSON DEFAULT NULL,
    click_count INT UNSIGNED DEFAULT 0,
    conversion_count INT UNSIGNED DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (niche_id) REFERENCES niches(id) ON DELETE SET NULL,
    INDEX idx_affiliate_user (user_id),
    INDEX idx_affiliate_niche (niche_id),
    INDEX idx_affiliate_campaign (campaign_tag)
) ENGINE=InnoDB;

-- ============================================================
-- AI PROMPT TEMPLATES
-- Modular prompt system for content generation
-- ============================================================
CREATE TABLE ai_prompt_templates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    name VARCHAR(100) NOT NULL,
    category ENUM(
        'story', 'product_recommendation', 'comparison',
        'productivity_tip', 'viral_hook', 'opinion',
        'list_post', 'wish_i_knew', 'general'
    ) NOT NULL,
    tone ENUM(
        'casual', 'professional', 'witty', 'inspirational',
        'controversial', 'educational', 'storytelling', 'urgent'
    ) DEFAULT 'casual',
    template TEXT NOT NULL,
    system_prompt TEXT NULL,
    variables JSON DEFAULT NULL,
    is_system TINYINT(1) DEFAULT 0,
    usage_count INT UNSIGNED DEFAULT 0,
    avg_engagement_score DECIMAL(5,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_prompts_category (category),
    INDEX idx_prompts_tone (tone),
    INDEX idx_prompts_user (user_id)
) ENGINE=InnoDB;
