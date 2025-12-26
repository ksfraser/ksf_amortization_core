<?php
namespace Ksfraser\Amortizations;

class LoanSummary
{
    public $id;
    public $borrower_id;
    public $borrower_type;
    public $amount_financed;
    public $interest_rate;
    public $loan_term_years;
    public $payments_per_year;
    public $first_payment_date;
    public $regular_payment;
    public $override_payment;
    public $loan_type;
    public $interest_calc_frequency;
    public $status;

    public function __construct(array $data = [])
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }
    }

    public function getNumPayments()
    {
        return (int)$this->loan_term_years * (int)$this->payments_per_year;
    }
}
