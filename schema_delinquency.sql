-- Migration: Payment History and Delinquency Tracking Tables
-- Purpose: Add tables for audit trail, event history, and delinquency classification
-- Created: 2025-12-11
-- Target Database: Multiple (FA, WordPress, SuiteCRM)

-- ============================================================
-- TABLE: loan_event_audit
-- PURPOSE: Comprehensive audit trail of all loan events
-- ============================================================
CREATE TABLE IF NOT EXISTS 0_ksf_loan_event_audit (
    id INT AUTO_INCREMENT PRIMARY KEY,
    loan_id INT NOT NULL,
    event_type VARCHAR(64) NOT NULL,
    event_date DATETIME NOT NULL,
    amount DECIMAL(15,2),
    status VARCHAR(32),  -- on_time, late, partial, missed, etc.
    metadata JSON,  -- Stores additional event context
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by VARCHAR(128),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_loan_id (loan_id),
    INDEX idx_event_type (event_type),
    INDEX idx_event_date (event_date),
    INDEX idx_status (status),
    FOREIGN KEY (loan_id) REFERENCES 0_ksf_loans_summary(id) ON DELETE CASCADE
);

-- ============================================================
-- TABLE: payment_history
-- PURPOSE: Summary statistics of payment activity per loan
-- ============================================================
CREATE TABLE IF NOT EXISTS 0_ksf_payment_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    loan_id INT NOT NULL UNIQUE,
    total_paid DECIMAL(15,2) DEFAULT 0.00,
    total_payments INT DEFAULT 0,
    on_time_count INT DEFAULT 0,
    late_count INT DEFAULT 0,
    partial_count INT DEFAULT 0,
    missed_count INT DEFAULT 0,
    average_payment DECIMAL(15,2) DEFAULT 0.00,
    max_payment DECIMAL(15,2) DEFAULT 0.00,
    min_payment DECIMAL(15,2) DEFAULT 0.00,
    total_interest_paid DECIMAL(15,2) DEFAULT 0.00,
    last_payment_date DATE,
    first_late_date DATE,
    days_since_last_payment INT DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_loan_id (loan_id),
    INDEX idx_updated_at (updated_at),
    FOREIGN KEY (loan_id) REFERENCES 0_ksf_loans_summary(id) ON DELETE CASCADE
);

-- ============================================================
-- TABLE: delinquency_status
-- PURPOSE: Current delinquency classification and risk assessment
-- ============================================================
CREATE TABLE IF NOT EXISTS 0_ksf_delinquency_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    loan_id INT NOT NULL UNIQUE,
    status VARCHAR(32) NOT NULL,  -- CURRENT, 30_DAYS_PAST_DUE, 60_DAYS_PAST_DUE, 90_PLUS_DAYS_PAST_DUE
    days_overdue INT DEFAULT 0,
    missed_payments INT DEFAULT 0,
    risk_score INT DEFAULT 0,  -- 0-100 scale
    risk_level VARCHAR(16),  -- LOW, MEDIUM, HIGH, CRITICAL
    pattern_type VARCHAR(32),  -- CURRENT, CHRONIC_LATE, RECENT_DETERIORATION, SPORADIC_PAYER
    trend VARCHAR(16),  -- IMPROVING, STABLE, DETERIORATING
    on_time_percentage DECIMAL(5,2) DEFAULT 100.00,
    late_percentage DECIMAL(5,2) DEFAULT 0.00,
    missed_percentage DECIMAL(5,2) DEFAULT 0.00,
    next_action_date DATE,
    last_action VARCHAR(255),
    last_action_date DATETIME,
    classification_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_loan_id (loan_id),
    INDEX idx_status (status),
    INDEX idx_risk_score (risk_score),
    INDEX idx_risk_level (risk_level),
    INDEX idx_pattern_type (pattern_type),
    INDEX idx_next_action_date (next_action_date),
    FOREIGN KEY (loan_id) REFERENCES 0_ksf_loans_summary(id) ON DELETE CASCADE
);

-- ============================================================
-- TABLE: collection_actions
-- PURPOSE: Track collection attempts and actions taken
-- ============================================================
CREATE TABLE IF NOT EXISTS 0_ksf_collection_actions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    loan_id INT NOT NULL,
    delinquency_status_id INT,
    action_type VARCHAR(64) NOT NULL,  -- reminder, contact, payment_plan, legal_notice, etc.
    description TEXT,
    action_date DATETIME NOT NULL,
    due_date DATE,
    completed_date DATETIME,
    result VARCHAR(64),  -- success, failed, pending, etc.
    notes TEXT,
    assigned_to VARCHAR(128),
    next_action VARCHAR(64),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_loan_id (loan_id),
    INDEX idx_action_type (action_type),
    INDEX idx_action_date (action_date),
    INDEX idx_due_date (due_date),
    INDEX idx_result (result),
    FOREIGN KEY (loan_id) REFERENCES 0_ksf_loans_summary(id) ON DELETE CASCADE,
    FOREIGN KEY (delinquency_status_id) REFERENCES 0_ksf_delinquency_status(id) ON DELETE SET NULL
);

-- ============================================================
-- TABLE: payment_arrangement
-- PURPOSE: Track payment plans and arrangements for delinquent loans
-- ============================================================
CREATE TABLE IF NOT EXISTS 0_ksf_payment_arrangement (
    id INT AUTO_INCREMENT PRIMARY KEY,
    loan_id INT NOT NULL,
    arrangement_type VARCHAR(32) NOT NULL,  -- payment_plan, forbearance, modification, etc.
    status VARCHAR(32) NOT NULL,  -- active, completed, defaulted, cancelled
    start_date DATE NOT NULL,
    end_date DATE,
    modified_payment DECIMAL(15,2),  -- New payment amount if modified
    modified_term INT,  -- New term length if modified
    description TEXT,
    created_date DATETIME NOT NULL,
    created_by VARCHAR(128),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_loan_id (loan_id),
    INDEX idx_status (status),
    INDEX idx_start_date (start_date),
    INDEX idx_arrangement_type (arrangement_type),
    FOREIGN KEY (loan_id) REFERENCES 0_ksf_loans_summary(id) ON DELETE CASCADE
);

-- ============================================================
-- ALTER EXISTING TABLES (if needed)
-- ============================================================

-- Add delinquency status foreign key to loans table if not exists
ALTER TABLE 0_ksf_loans_summary 
ADD COLUMN IF NOT EXISTS delinquency_status_id INT DEFAULT NULL,
ADD FOREIGN KEY (delinquency_status_id) 
    REFERENCES 0_ksf_delinquency_status(id) 
    ON DELETE SET NULL;

-- Add tracking flags to existing events table
ALTER TABLE 0_ksf_loan_events 
ADD COLUMN IF NOT EXISTS status VARCHAR(32),
ADD COLUMN IF NOT EXISTS metadata JSON,
ADD INDEX IF NOT EXISTS idx_status (status);

-- ============================================================
-- INDEXES FOR PERFORMANCE
-- ============================================================

-- Query optimization for audit trail
CREATE INDEX IF NOT EXISTS idx_audit_loan_date ON 0_ksf_loan_event_audit(loan_id, event_date DESC);
CREATE INDEX IF NOT EXISTS idx_audit_type_date ON 0_ksf_loan_event_audit(event_type, event_date DESC);
CREATE INDEX IF NOT EXISTS idx_audit_status_date ON 0_ksf_loan_event_audit(status, event_date DESC);

-- Query optimization for delinquency
CREATE INDEX IF NOT EXISTS idx_delinq_status_date ON 0_ksf_delinquency_status(status, updated_at DESC);
CREATE INDEX IF NOT EXISTS idx_delinq_risk ON 0_ksf_delinquency_status(risk_level, risk_score DESC);
CREATE INDEX IF NOT EXISTS idx_delinq_next_action ON 0_ksf_delinquency_status(next_action_date, status);

-- Query optimization for collections
CREATE INDEX IF NOT EXISTS idx_collection_loan_date ON 0_ksf_collection_actions(loan_id, action_date DESC);
CREATE INDEX IF NOT EXISTS idx_collection_type_result ON 0_ksf_collection_actions(action_type, result);
CREATE INDEX IF NOT EXISTS idx_collection_due_date ON 0_ksf_collection_actions(due_date, result);

-- Query optimization for payment arrangements
CREATE INDEX IF NOT EXISTS idx_arrangement_loan_status ON 0_ksf_payment_arrangement(loan_id, status);
CREATE INDEX IF NOT EXISTS idx_arrangement_type_status ON 0_ksf_payment_arrangement(arrangement_type, status);

-- ============================================================
-- VIEWS FOR REPORTING
-- ============================================================

-- View: Current Delinquency Summary
CREATE OR REPLACE VIEW vw_current_delinquency AS
SELECT 
    d.id,
    d.loan_id,
    l.id as loan_summary_id,
    d.status,
    d.days_overdue,
    d.missed_payments,
    d.risk_score,
    d.risk_level,
    d.pattern_type,
    d.trend,
    d.next_action_date,
    p.total_paid,
    p.total_payments,
    p.on_time_count,
    p.late_count,
    p.last_payment_date,
    d.updated_at
FROM 0_ksf_delinquency_status d
LEFT JOIN 0_ksf_payment_history p ON d.loan_id = p.loan_id
LEFT JOIN 0_ksf_loans_summary l ON d.loan_id = l.id;

-- View: Collection Activity Summary
CREATE OR REPLACE VIEW vw_collection_summary AS
SELECT 
    loan_id,
    COUNT(*) as total_actions,
    COUNT(CASE WHEN result = 'success' THEN 1 END) as successful_actions,
    COUNT(CASE WHEN result = 'failed' THEN 1 END) as failed_actions,
    COUNT(CASE WHEN result = 'pending' THEN 1 END) as pending_actions,
    MAX(action_date) as last_action_date,
    MAX(CASE WHEN result = 'pending' THEN due_date END) as next_due_date
FROM 0_ksf_collection_actions
GROUP BY loan_id;
