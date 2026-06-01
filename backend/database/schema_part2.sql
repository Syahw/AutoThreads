-- AutoThreads Database Schema - Part 2
-- Continue from schema.sql

USE autothreads;

-- ============================================================
-- GENERATED POSTS
-- AI-generated content stored before scheduling
-- ============================================================
CREATE TABLE generated_posts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    niche_id BIGINT UNSIGNED NULL,
    topic_id BIGINT UNSIGNED NULL,
    affiliate_link_id BIGINT UNSIGNED NULL,
    prompt_template_id BIGINT UNSIGNED NULL,
    content TEXT NOT NULL,
    hook VARCHAR(500) NULL,
    cta VARCHAR(500) NULL,
    hashtags JSON DEFAULT NULL,
    category ENUM(
        'story', 'product_recommendation', 'comparison',
        'productivity_tip', 'viral_hook', 'opinion',
        'list_post', 'wish_i_knew', 'general'
    ) DEFAULT 'general',
    tone VARCHAR(50) NULL,
    writing_style VARCHAR(50) NULL,
    quality_score DECIMAL(5,2) DEFAULT 0.00,
    humanization_score DECIMAL(5,2) DEFAULT 0.00,
    status ENUM('draft', 'approved', 'rejected', 'scheduled', 'posted', 'failed') DEFAULT 'draft',
    ai_model VARCHAR(50) DEFAULT 'gpt-4',
    tokens_used INT UNSIGNED DEFAULT 0,
    generation_cost DECIMAL(8,4) DEFAULT 0.0000,
    variations_count TINYINT UNSIGNED DEFAULT 1,
    parent_post_id BIGINT UNSIGNED NULL,
    metadata JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (niche_id) REFERENCES niches(id) ON DELETE SET NULL,
    FOREIGN KEY (topic_id) REFERENCES topics(id) ON DELETE SET NULL,
    FOREIGN KEY (affiliate_link_id) REFERENCES affiliate_links(id) ON DELETE SET NULL,
    FOREIGN KEY (prompt_template_id) REFERENCES ai_prompt_templates(id) ON DELETE SET NULL,
    FOREIGN KEY (parent_post_id) REFERENCES generated_posts(id) ON DELETE SET NULL,
    INDEX idx_generated_user_status (user_id, status),
    INDEX idx_generated_niche (niche_id),
    INDEX idx_generated_quality (quality_score DESC),
    INDEX idx_generated_created (created_at DESC),
    FULLTEXT INDEX idx_generated_content_ft (content)
) ENGINE=InnoDB;

-- ============================================================
-- SCHEDULED POSTS
-- Queue of posts waiting to be published
-- ============================================================
CREATE TABLE scheduled_posts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    generated_post_id BIGINT UNSIGNED NOT NULL,
    threads_account_id BIGINT UNSIGNED NOT NULL,
    scheduled_at TIMESTAMP NOT NULL,
    status ENUM('queued', 'processing', 'posted', 'failed', 'cancelled') DEFAULT 'queued',
    retry_count TINYINT UNSIGNED DEFAULT 0,
    max_retries TINYINT UNSIGNED DEFAULT 3,
    last_error TEXT NULL,
    posted_at TIMESTAMP NULL,
    threads_post_id VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (generated_post_id) REFERENCES generated_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (threads_account_id) REFERENCES threads_accounts(id) ON DELETE CASCADE,
    INDEX idx_scheduled_time (scheduled_at),
    INDEX idx_scheduled_status (status),
    INDEX idx_scheduled_user (user_id),
    INDEX idx_scheduled_queue (status, scheduled_at)
) ENGINE=InnoDB;

-- ============================================================
-- POSTING LOGS
-- Detailed log of all posting attempts
-- ============================================================
CREATE TABLE posting_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    scheduled_post_id BIGINT UNSIGNED NULL,
    threads_account_id BIGINT UNSIGNED NOT NULL,
    action ENUM('post', 'retry', 'delete', 'edit') DEFAULT 'post',
    status ENUM('success', 'failed', 'timeout', 'rate_limited') NOT NULL,
    threads_post_id VARCHAR(100) NULL,
    request_payload JSON NULL,
    response_payload JSON NULL,
    error_message TEXT NULL,
    response_time_ms INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (scheduled_post_id) REFERENCES scheduled_posts(id) ON DELETE SET NULL,
    FOREIGN KEY (threads_account_id) REFERENCES threads_accounts(id) ON DELETE CASCADE,
    INDEX idx_posting_logs_user (user_id),
    INDEX idx_posting_logs_status (status),
    INDEX idx_posting_logs_created (created_at DESC)
) ENGINE=InnoDB;

-- ============================================================
-- ANALYTICS
-- Engagement tracking for posted content
-- ============================================================
CREATE TABLE analytics (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    scheduled_post_id BIGINT UNSIGNED NULL,
    generated_post_id BIGINT UNSIGNED NULL,
    threads_post_id VARCHAR(100) NOT NULL,
    impressions INT UNSIGNED DEFAULT 0,
    likes INT UNSIGNED DEFAULT 0,
    comments INT UNSIGNED DEFAULT 0,
    reposts INT UNSIGNED DEFAULT 0,
    quotes INT UNSIGNED DEFAULT 0,
    link_clicks INT UNSIGNED DEFAULT 0,
    profile_visits INT UNSIGNED DEFAULT 0,
    followers_gained INT UNSIGNED DEFAULT 0,
    ctr DECIMAL(5,2) DEFAULT 0.00,
    engagement_rate DECIMAL(5,2) DEFAULT 0.00,
    collected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (scheduled_post_id) REFERENCES scheduled_posts(id) ON DELETE SET NULL,
    FOREIGN KEY (generated_post_id) REFERENCES generated_posts(id) ON DELETE SET NULL,
    INDEX idx_analytics_user (user_id),
    INDEX idx_analytics_post (threads_post_id),
    INDEX idx_analytics_engagement (engagement_rate DESC),
    INDEX idx_analytics_collected (collected_at DESC)
) ENGINE=InnoDB;

-- ============================================================
-- ENGAGEMENT SCORES
-- AI optimization scoring for content performance
-- ============================================================
CREATE TABLE engagement_scores (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    generated_post_id BIGINT UNSIGNED NOT NULL,
    hook_score DECIMAL(5,2) DEFAULT 0.00,
    cta_score DECIMAL(5,2) DEFAULT 0.00,
    readability_score DECIMAL(5,2) DEFAULT 0.00,
    engagement_prediction DECIMAL(5,2) DEFAULT 0.00,
    overall_score DECIMAL(5,2) DEFAULT 0.00,
    scoring_model VARCHAR(50) DEFAULT 'v1',
    factors JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (generated_post_id) REFERENCES generated_posts(id) ON DELETE CASCADE,
    INDEX idx_scores_user (user_id),
    INDEX idx_scores_overall (overall_score DESC)
) ENGINE=InnoDB;

-- ============================================================
-- BLACKLISTED WORDS
-- Spam prevention and content moderation
-- ============================================================
CREATE TABLE blacklisted_words (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    word VARCHAR(100) NOT NULL,
    category ENUM('spam', 'offensive', 'ai_giveaway', 'brand_risk', 'custom') DEFAULT 'custom',
    is_global TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_blacklist_word (word),
    INDEX idx_blacklist_user (user_id)
) ENGINE=InnoDB;

-- ============================================================
-- AI USAGE LOGS
-- Track API usage and costs
-- ============================================================
CREATE TABLE ai_usage_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    model VARCHAR(50) NOT NULL,
    action ENUM('generate', 'rewrite', 'score', 'topic_generate', 'optimize') NOT NULL,
    prompt_tokens INT UNSIGNED DEFAULT 0,
    completion_tokens INT UNSIGNED DEFAULT 0,
    total_tokens INT UNSIGNED DEFAULT 0,
    cost DECIMAL(8,4) DEFAULT 0.0000,
    response_time_ms INT UNSIGNED NULL,
    success TINYINT(1) DEFAULT 1,
    error_message TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_ai_usage_user (user_id),
    INDEX idx_ai_usage_created (created_at DESC),
    INDEX idx_ai_usage_action (action)
) ENGINE=InnoDB;
