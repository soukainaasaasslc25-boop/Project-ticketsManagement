-- =============================================================================
-- PROJECT  : Student Requests & Complaints Ticket Tracking System
-- FILE     : schema.sql
-- PURPOSE  : Complete database schema — clean, simple, beginner-friendly
-- ENGINE   : InnoDB | CHARSET: utf8mb4 | MySQL 8+
-- =============================================================================

-- -----------------------------------------------------------------------------
-- Create and select the database
-- -----------------------------------------------------------------------------

CREATE DATABASE IF NOT EXISTS `ticket_system`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `ticket_system`;

-- Disable foreign key checks during table creation to avoid ordering issues
SET FOREIGN_KEY_CHECKS = 0;

-- =============================================================================
-- TABLE 1: users
--
-- Stores BOTH administrators and students in a single table.
-- We only keep what we actually need — nothing more.
--
-- Admins: group_name = NULL, filiere = NULL
-- Students: group_name and filiere are filled automatically during import
-- =============================================================================

CREATE TABLE IF NOT EXISTS `users` (
    `id`             INT UNSIGNED    NOT NULL AUTO_INCREMENT,

    -- 'admin'   → accesses admin dashboard
    -- 'student' → accesses student dashboard
    `role`           ENUM('admin', 'student') NOT NULL DEFAULT 'student',

    -- Used to log in — generated automatically from last_name + first_name
    -- Example: ASAAS + SOUKAINA → asaassoukaina
    -- Must be unique across all users
    `username`       VARCHAR(100)    NOT NULL,

    `first_name`     VARCHAR(100)    NOT NULL,
    `last_name`      VARCHAR(100)    NOT NULL,

    -- Always stored hashed with password_hash() — NEVER plain text
    `password_hash`  VARCHAR(255)    NOT NULL,

    -- Example: DWB102, DMB201
    -- NULL for admin accounts
    `group_name`     VARCHAR(50)     NULL DEFAULT NULL,

    -- Auto-generated from group_name prefix:
    --   DWB* → "Web Development"
    --   DMB* → "Mobile Development"
    -- NULL for admin accounts
    `filiere`        VARCHAR(100)    NULL DEFAULT NULL,

    -- Imported students start as 'inactive'
    -- On first successful login → automatically set to 'active'
    -- Admins are always 'active'
    `account_status` ENUM('active', 'inactive') NOT NULL DEFAULT 'inactive',

    -- Updated every time the user logs in successfully
    `last_login_at`  DATETIME        NULL DEFAULT NULL,

    `created_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                     ON UPDATE CURRENT_TIMESTAMP,

    -- -------------------------------------------------------------------------
    PRIMARY KEY (`id`),

    -- Username is the ONLY login key — must be unique
    UNIQUE KEY `uq_users_username` (`username`),

    -- Common filter: find all students, or all admins
    INDEX `idx_users_role`           (`role`),

    -- Common filter: find students by filiere or group
    INDEX `idx_users_filiere`        (`filiere`),
    INDEX `idx_users_group_name`     (`group_name`),

    -- Common filter: find inactive students pending activation
    INDEX `idx_users_account_status` (`account_status`)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Stores administrators and students — unified table';


-- =============================================================================
-- TABLE 2: categories
--
-- Organizes tickets into logical categories.
-- Each category belongs to ONE type: 'request' or 'complaint'.
--
-- Examples:
--   type='request'   → "Academic Documents", "Administrative Services"
--   type='complaint' → "Administrative Issue", "Infrastructure"
-- =============================================================================

CREATE TABLE IF NOT EXISTS `categories` (
    `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,

    -- Determines which ticket type this category applies to
    `type`        ENUM('request', 'complaint') NOT NULL,

    `name`        VARCHAR(150)    NOT NULL,

    -- Optional longer explanation shown to the student
    `description` TEXT            NULL DEFAULT NULL,

    -- 0 = hidden from ticket creation form, 1 = visible
    `is_active`   TINYINT(1)      NOT NULL DEFAULT 1,

    `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                  ON UPDATE CURRENT_TIMESTAMP,

    -- -------------------------------------------------------------------------
    PRIMARY KEY (`id`),

    INDEX `idx_categories_type`      (`type`),
    INDEX `idx_categories_is_active` (`is_active`)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Main ticket categories — request types and complaint types';


-- =============================================================================
-- TABLE 3: subcategories
--
-- More specific classification under each category.
--
-- Example:
--   Category: "Academic Documents"
--   Subcategories: "Transcript Request", "Enrollment Certificate", ...
-- =============================================================================

CREATE TABLE IF NOT EXISTS `subcategories` (
    `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,

    -- Every subcategory belongs to exactly one parent category
    `category_id` INT UNSIGNED    NOT NULL,

    `name`        VARCHAR(150)    NOT NULL,
    `description` TEXT            NULL DEFAULT NULL,

    `is_active`   TINYINT(1)      NOT NULL DEFAULT 1,

    `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- -------------------------------------------------------------------------
    PRIMARY KEY (`id`),

    INDEX `idx_subcategories_category_id` (`category_id`),
    INDEX `idx_subcategories_is_active`   (`is_active`),

    -- If a category is deleted, its subcategories are also deleted
    CONSTRAINT `fk_subcategories_category`
        FOREIGN KEY (`category_id`)
        REFERENCES `categories` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Subcategories — children of main categories';


-- =============================================================================
-- TABLE 4: tickets
--
-- THE CORE TABLE of the entire system.
-- Every student request and complaint is a ticket.
--
-- Lifecycle:
--   draft → new → opened → in_progress → completed
--                                       → rejected
--
-- Notes:
--   - 'draft'       : student saved but not yet submitted
--   - 'new'         : submitted, admin has not opened yet
--   - 'opened'      : admin acknowledged and opened it
--   - 'in_progress' : admin is actively working on it
--   - 'completed'   : resolved
--   - 'rejected'    : refused with a reason
-- =============================================================================

CREATE TABLE IF NOT EXISTS `tickets` (
    `id`               INT UNSIGNED    NOT NULL AUTO_INCREMENT,

    -- Human-readable unique reference shown to users
    -- Format: TKT-YYYY-NNNNN (e.g. TKT-2025-00001)
    -- Generated by PHP when the student submits the ticket
    `reference`        VARCHAR(20)     NOT NULL,

    -- The student who created this ticket
    `user_id`          INT UNSIGNED    NOT NULL,

    -- The admin who was assigned to handle this ticket
    -- NULL means not yet assigned
    `assigned_to`      INT UNSIGNED    NULL DEFAULT NULL,

    -- Required: which category does this ticket fall under
    `category_id`      INT UNSIGNED    NOT NULL,

    -- Optional: more specific sub-classification
    `subcategory_id`   INT UNSIGNED    NULL DEFAULT NULL,

    -- Mirrors the category type for easy filtering
    `type`             ENUM('request', 'complaint') NOT NULL,

    -- Urgency level set by the student
    `priority`         ENUM('low', 'medium', 'high', 'urgent') NOT NULL DEFAULT 'medium',

    -- Short title for the ticket (like an email subject)
    `subject`          VARCHAR(255)    NOT NULL,

    -- Full description written by the student
    `description`      TEXT            NOT NULL,

    -- Current stage in the ticket lifecycle
    `status`           ENUM('draft', 'new', 'opened', 'in_progress', 'completed', 'rejected')
                       NOT NULL DEFAULT 'draft',

    -- Filled in by the admin when rejecting a ticket — explains why
    `rejection_reason` TEXT            NULL DEFAULT NULL,

    -- Set when student clicks "Submit" (moves from draft → new)
    `submitted_at`     DATETIME        NULL DEFAULT NULL,

    -- Set when admin marks as completed or rejected
    `resolved_at`      DATETIME        NULL DEFAULT NULL,

    `created_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                       ON UPDATE CURRENT_TIMESTAMP,

    -- -------------------------------------------------------------------------
    PRIMARY KEY (`id`),

    UNIQUE KEY `uq_tickets_reference` (`reference`),

    -- Indexes for the most common query filters in admin dashboard
    INDEX `idx_tickets_user_id`        (`user_id`),
    INDEX `idx_tickets_assigned_to`    (`assigned_to`),
    INDEX `idx_tickets_category_id`    (`category_id`),
    INDEX `idx_tickets_subcategory_id` (`subcategory_id`),
    INDEX `idx_tickets_status`         (`status`),
    INDEX `idx_tickets_type`           (`type`),
    INDEX `idx_tickets_priority`       (`priority`),
    INDEX `idx_tickets_submitted_at`   (`submitted_at`),

    -- Student who created the ticket — cannot delete user if they have tickets
    CONSTRAINT `fk_tickets_user`
        FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    -- Assigned admin — unassign if admin account is deleted
    CONSTRAINT `fk_tickets_assigned_to`
        FOREIGN KEY (`assigned_to`)
        REFERENCES `users` (`id`)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

    -- Category — cannot delete category if tickets use it
    CONSTRAINT `fk_tickets_category`
        FOREIGN KEY (`category_id`)
        REFERENCES `categories` (`id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    -- Subcategory — clear the subcategory if it gets deleted
    CONSTRAINT `fk_tickets_subcategory`
        FOREIGN KEY (`subcategory_id`)
        REFERENCES `subcategories` (`id`)
        ON DELETE SET NULL
        ON UPDATE CASCADE

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Core table — all student requests and complaints as tickets';


-- =============================================================================
-- TABLE 5: ticket_responses
--
-- The conversation thread for each ticket.
-- Both students and admins write messages here.
--
-- is_internal = 1 → admin-only internal note (student CANNOT see it)
-- is_internal = 0 → regular reply visible to both student and admin
-- =============================================================================

CREATE TABLE IF NOT EXISTS `ticket_responses` (
    `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,

    -- Which ticket this message belongs to
    `ticket_id`   INT UNSIGNED    NOT NULL,

    -- Who wrote this message (student or admin)
    `sender_id`   INT UNSIGNED    NOT NULL,

    `message`     TEXT            NOT NULL,

    -- 1 = internal admin note (hidden from student)
    -- 0 = public reply (student and admin both see it)
    `is_internal` TINYINT(1)      NOT NULL DEFAULT 0,

    `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                  ON UPDATE CURRENT_TIMESTAMP,

    -- -------------------------------------------------------------------------
    PRIMARY KEY (`id`),

    INDEX `idx_responses_ticket_id` (`ticket_id`),
    INDEX `idx_responses_sender_id` (`sender_id`),

    -- Delete all responses when the ticket is deleted
    CONSTRAINT `fk_responses_ticket`
        FOREIGN KEY (`ticket_id`)
        REFERENCES `tickets` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    -- Cannot delete a user who has sent responses
    CONSTRAINT `fk_responses_sender`
        FOREIGN KEY (`sender_id`)
        REFERENCES `users` (`id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Conversation thread per ticket between student and admin';


-- =============================================================================
-- TABLE 6: ticket_attachments
--
-- Stores files uploaded to a ticket.
--
-- A file can be attached to:
--   a) The ticket itself (response_id = NULL) — uploaded at submission time
--   b) A specific response (response_id = X) — uploaded with a reply
--
-- Allowed: PDF, DOCX, XLSX, PNG, JPG
-- Storage path: uploads/YYYY/MM/random_secure_name.ext
-- =============================================================================

CREATE TABLE IF NOT EXISTS `ticket_attachments` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,

    `ticket_id`     INT UNSIGNED    NOT NULL,

    -- NULL = attached to the ticket; integer = attached to a response
    `response_id`   INT UNSIGNED    NULL DEFAULT NULL,

    -- The user (student or admin) who uploaded this file
    `uploaded_by`   INT UNSIGNED    NOT NULL,

    -- Original filename as uploaded by the user (e.g. "my_transcript.pdf")
    `original_name` VARCHAR(255)    NOT NULL,

    -- Secure random name stored on disk (e.g. "f3a9b21c7d.pdf")
    `stored_name`   VARCHAR(255)    NOT NULL,

    -- Relative path on the server (e.g. "uploads/2025/05/f3a9b21c7d.pdf")
    `file_path`     VARCHAR(500)    NOT NULL,

    -- PHP-validated MIME type (e.g. "application/pdf", "image/jpeg")
    `mime_type`     VARCHAR(100)    NOT NULL,

    -- File size in bytes — used to enforce maximum upload size
    `file_size`     INT UNSIGNED    NOT NULL DEFAULT 0,

    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- -------------------------------------------------------------------------
    PRIMARY KEY (`id`),

    INDEX `idx_attachments_ticket_id`   (`ticket_id`),
    INDEX `idx_attachments_response_id` (`response_id`),
    INDEX `idx_attachments_uploaded_by` (`uploaded_by`),

    -- Delete attachments when their ticket is deleted
    CONSTRAINT `fk_attachments_ticket`
        FOREIGN KEY (`ticket_id`)
        REFERENCES `tickets` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    -- Keep the file even if the response is deleted (just unlink)
    CONSTRAINT `fk_attachments_response`
        FOREIGN KEY (`response_id`)
        REFERENCES `ticket_responses` (`id`)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

    -- Cannot delete a user who uploaded files
    CONSTRAINT `fk_attachments_uploader`
        FOREIGN KEY (`uploaded_by`)
        REFERENCES `users` (`id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Files uploaded to tickets or ticket responses';


-- =============================================================================
-- TABLE 7: remember_tokens
--
-- Implements a secure "Remember Me" feature using the selector/validator pattern.
--
-- Why this is more secure than a simple token in the users table:
--   → The validator is NEVER stored in plain text in the database
--   → An attacker who reads the DB cannot use the tokens to log in
--   → Timing-safe comparison prevents timing attacks
--
-- How it works:
--   1. User checks "Remember Me" on login
--   2. PHP generates: selector (random, 32 bytes hex) + validator (random, 32 bytes hex)
--   3. Database stores: selector (plain) + hash('sha256', validator)
--   4. Browser cookie stores: selector + ":" + validator (plain)
--   5. On next visit: read cookie → find row by selector → compare hashed validator
--   6. If match and not expired → log the user in automatically
-- =============================================================================

CREATE TABLE IF NOT EXISTS `remember_tokens` (
    `id`               INT UNSIGNED    NOT NULL AUTO_INCREMENT,

    `user_id`          INT UNSIGNED    NOT NULL,

    -- Used to FIND the token row in the database (stored in cookie, plain in DB)
    `selector`         VARCHAR(64)     NOT NULL,

    -- Validator is stored HASHED — never plain text
    -- Compared using hash_equals() for timing-safe verification
    `hashed_validator` VARCHAR(255)    NOT NULL,

    -- Typically set to 30 days from creation date
    `expires_at`       DATETIME        NOT NULL,

    `created_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- -------------------------------------------------------------------------
    PRIMARY KEY (`id`),

    -- Selector must be unique — used as lookup key
    UNIQUE KEY `uq_remember_tokens_selector` (`selector`),

    INDEX `idx_remember_tokens_user_id`    (`user_id`),
    INDEX `idx_remember_tokens_expires_at` (`expires_at`),

    -- Delete all tokens when the user is deleted
    CONSTRAINT `fk_remember_tokens_user`
        FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Secure remember-me tokens — selector/validator pattern';


-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- END OF SCHEMA
-- =============================================================================
