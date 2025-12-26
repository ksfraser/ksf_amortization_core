-- Table for loan types
CREATE TABLE IF NOT EXISTS ksf_amort_loan_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(64) NOT NULL,
    description VARCHAR(255) DEFAULT ''
);

-- Table for interest calculation frequencies
CREATE TABLE IF NOT EXISTS ksf_amort_interest_calc_frequencies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(64) NOT NULL,
    description VARCHAR(255) DEFAULT ''
);

-- Prepopulate loan_types
INSERT INTO ksf_amort_loan_types (name, description) VALUES
('Auto', 'Auto loan'),
('Mortgage', 'Mortgage loan'),
('Other', 'Other loan type');

-- Prepopulate interest_calc_frequencies
INSERT INTO ksf_amort_interest_calc_frequencies (name, description) VALUES
('daily', 'Daily'),
('weekly', 'Weekly'),
('bi-weekly', 'Bi-Weekly'),
('semi-monthly', 'Semi-Monthly'),
('monthly', 'Monthly'),
('semi-annual', 'Semi-Annual'),
('annual', 'Annual');

-- Table: 0_ksf_selectors
CREATE TABLE IF NOT EXISTS 0_ksf_selectors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    selector_name VARCHAR(32) NOT NULL,
    option_name VARCHAR(64) NOT NULL,
    option_value VARCHAR(64) NOT NULL
);

-- Pre-populate with current values
INSERT INTO 0_ksf_selectors (selector_name, option_name, option_value) VALUES
    ('payment_frequency', 'Annual', 'annual'),
    ('payment_frequency', 'Semi-Annual', 'semi-annual'),
    ('payment_frequency', 'Monthly', 'monthly'),
    ('payment_frequency', 'Semi-Monthly', 'semi-monthly'),
    ('payment_frequency', 'Bi-Weekly', 'bi-weekly'),
    ('payment_frequency', 'Weekly', 'weekly'),
    ('borrower_type', 'Customer', 'Customer'),
    ('borrower_type', 'Supplier', 'Supplier'),
    ('borrower_type', 'Employee', 'Employee');
