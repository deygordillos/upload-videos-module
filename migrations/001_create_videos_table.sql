-- Migration: Create videos table
-- Version: 1.0
-- Author: SimpleData Corp
-- Date: 2025-12-10
-- Description: Table for storing video metadata across multiple projects

-- Drop table if exists (for development)
DROP TABLE IF EXISTS `videos`;

-- Create videos table
CREATE TABLE `videos` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id` VARCHAR(50) NOT NULL COMMENT 'Project identifier for multi-tenancy',
  `video_identifier` VARCHAR(100) NOT NULL COMMENT 'Unique identifier for the video',
  `original_filename` VARCHAR(255) NOT NULL COMMENT 'Original filename from client',
  `file_path` VARCHAR(500) NOT NULL COMMENT 'Relative path to stored file',
  `file_size` BIGINT UNSIGNED NOT NULL COMMENT 'File size in bytes',
  `mime_type` VARCHAR(100) NOT NULL COMMENT 'MIME type of the video',
  `duration` INT UNSIGNED NULL COMMENT 'Video duration in seconds',
  `width` INT UNSIGNED NULL COMMENT 'Video width in pixels',
  `height` INT UNSIGNED NULL COMMENT 'Video height in pixels',
  `upload_ip` VARCHAR(45) NULL COMMENT 'IP address of uploader',
  `user_agent` VARCHAR(255) NULL COMMENT 'User agent string',
  `metadata` JSON NULL COMMENT 'Additional metadata in JSON format',
  `status` ENUM('pending', 'processing', 'completed', 'failed') NOT NULL DEFAULT 'pending',
  `error_message` TEXT NULL COMMENT 'Error details if upload/processing failed',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Soft delete timestamp',
  
  PRIMARY KEY (`id`),
  INDEX `idx_project_id` (`project_id`),
  INDEX `idx_video_identifier` (`video_identifier`),
  INDEX `idx_status` (`status`),
  INDEX `idx_created_at` (`created_at`),
  INDEX `idx_deleted_at` (`deleted_at`),
  UNIQUE KEY `uk_project_video` (`project_id`, `video_identifier`, `deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Videos storage metadata table';

-- Create audit log table
CREATE TABLE `video_audit_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `video_id` BIGINT UNSIGNED NOT NULL,
  `action` VARCHAR(50) NOT NULL COMMENT 'Action performed (upload, update, delete, etc)',
  `user_id` VARCHAR(100) NULL COMMENT 'User identifier if available',
  `ip_address` VARCHAR(45) NULL,
  `details` JSON NULL COMMENT 'Additional details about the action',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  INDEX `idx_video_id` (`video_id`),
  INDEX `idx_action` (`action`),
  INDEX `idx_created_at` (`created_at`),
  FOREIGN KEY (`video_id`) REFERENCES `videos`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Audit trail for video operations';
