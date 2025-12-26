-- Table: 0_ksf_loans_summary
CREATE TABLE IF NOT EXISTS 0_ksf_loans_summary (
    id INT AUTO_INCREMENT PRIMARY KEY,
    borrower_id INT NOT NULL,
    borrower_type VARCHAR(32) NOT NULL,
    amount_financed DECIMAL(15,2) NOT NULL,
    interest_rate DECIMAL(5,2) NOT NULL,
    loan_term_years INT NOT NULL,
    payments_per_year INT NOT NULL,
    -- num_payments is now a calculated field: loan_term_years * payments_per_year
    first_payment_date DATE NOT NULL,
    regular_payment DECIMAL(15,2) NOT NULL,
    override_payment TINYINT(1) DEFAULT 0,
    loan_type VARCHAR(32),
    interest_calc_frequency VARCHAR(32),
    status VARCHAR(16) DEFAULT 'active'
);

-- Table: 0_ksf_amortization_staging
CREATE TABLE IF NOT EXISTS 0_ksf_amortization_staging (
    id INT AUTO_INCREMENT PRIMARY KEY,
    loan_id INT NOT NULL,
    payment_date DATE NOT NULL,
    payment_amount DECIMAL(15,2) NOT NULL,
    principal_portion DECIMAL(15,2) NOT NULL,
    interest_portion DECIMAL(15,2) NOT NULL,
    remaining_balance DECIMAL(15,2) NOT NULL,
    posted_to_gl TINYINT(1) DEFAULT 0,
    trans_no INT,
    trans_type INT,
    voided TINYINT(1) DEFAULT 0,
    FOREIGN KEY (loan_id) REFERENCES 0_ksf_loans_summary(id)
);
