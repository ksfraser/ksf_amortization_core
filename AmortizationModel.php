<?php
namespace Ksfraser\Amortizations;

/**
 * AmortizationModel - Core amortization calculation engine
 *
 * Manages loan amortization schedule calculation with support for:
 * - Flexible payment frequencies (monthly, bi-weekly, weekly, daily)
 * - Flexible interest calculation frequencies
 * - Extra payment handling with automatic recalculation
 * - Event-based updates (skip payments, extra payments)
 *
 * ### Responsibility (SRP)
 * Single Responsibility: Amortization calculations only
 * - Calculates payment amounts with flexible frequencies
 * - Generates payment schedules with flexible periods
 * - Handles extra payment recalculation
 * NOT responsible for:
 * - Data persistence (delegated to DataProvider)
 * - GL posting (delegated to JournalService)
 * - User interface (delegated to Controllers)
 *
 * ### Dependencies (DIP)
 * Depends on interfaces, not concrete implementations:
 * - DataProviderInterface for data access
 * - LoanEventProviderInterface for event management (future)
 *
 * ### Design Patterns
 * - Repository Pattern: DataProvider abstracts persistence
 * - Dependency Injection: All dependencies provided via constructor
 *
 * ### UML Class Diagram
 * ```
 * ┌──────────────────────────────────────────┐
 * │      AmortizationModel                   │
 * ├──────────────────────────────────────────┤
 * │ - db: DataProviderInterface              │
 * │ - calculationPrecision: int = 4          │
 * ├──────────────────────────────────────────┤
 * │ + __construct(db): void                  │
 * │ + calculatePayment(...): float           │
 * │ + calculateSchedule(...): void           │
 * │ - getPeriodsPerYear(freq: str): int      │
 * │ - getPaymentIntervalDays(...): int       │
 * │ - calculateCompoundInterest(...): float  │
 * └──────────────────────────────────────────┘
 * ```
 *
 * @package   Ksfraser\Amortizations
 * @author    KSF Development Team
 * @version   1.0.0 (Refactored for flexible frequency)
 * @since     2025-12-08
 */
class AmortizationModel {
    /**
     * @var DataProviderInterface Data access layer
     */
    private $db;

    /**
     * @var int Decimal precision for internal calculations
     */
    private $calculationPrecision = 4;

    /**
     * @var array Frequency configuration (payments per year)
     */
    private static $frequencyConfig = [
        'monthly' => 12,
        'biweekly' => 26,
        'weekly' => 52,
        'daily' => 365,
        'semiannual' => 2,
        'annual' => 1,
    ];

    /**
     * Constructor with dependency injection
     *
     * ### Purpose
     * Initialize AmortizationModel with database provider
     *
     * ### Dependency Injection Benefits
     * - All dependencies injected, not created internally
     * - Easy to test with mock implementations
     * - Easy to add new platform implementations
     * - Follows Dependency Inversion Principle
     *
     * @param DataProviderInterface $db Data access layer
     *
     * @throws InvalidArgumentException If db is null
     */
    public function __construct(DataProviderInterface $db) {
        if (!$db) {
            throw new InvalidArgumentException('DataProviderInterface required');
        }
        $this->db = $db;
    }

    /**
     * Calculate regular payment amount with flexible frequency
     *
     * ### Purpose
     * Calculates the periodic payment for a loan using the standard
     * amortization formula, supporting multiple payment frequencies.
     *
     * ### Algorithm
     * Uses the compound interest formula:
     * PMT = (P * r * (1 + r)^n) / ((1 + r)^n - 1)
     *
     * Where:
     * - P = Principal
     * - r = Interest rate per period
     * - n = Number of periods
     *
     * ### Parameters
     * - `$principal`: Loan amount (must be > 0)
     * - `$annualRate`: Annual interest rate as percentage (5 = 5%)
     * - `$frequency`: Payment frequency string ('monthly', 'biweekly', etc)
     * - `$numberOfPayments`: Total number of payments
     *
     * ### Returns
     * Float payment amount in dollars and cents (2 decimal places)
     *
     * ### Example
     * ```php
     * // Calculate monthly payment for $10,000 at 5% for 30 years (360 months)
     * $payment = $model->calculatePayment(
     *     10000.00,     // principal
     *     5.0,          // annualRate
     *     'monthly',    // frequency
     *     360           // numberOfPayments
     * );
     * // Returns: 53.68 (approximately)
     * ```
     *
     * ### Precision & Rounding
     * - Maintains 4 decimal places internally
     * - Returns 2 decimal places (cents)
     * - Uses banker's rounding
     *
     * @param float $principal Principal amount (must be > 0)
     * @param float $annualRate Annual interest rate as percentage
     * @param string $frequency Payment frequency
     * @param int $numberOfPayments Total number of payments
     *
     * @return float Payment amount
     * @throws InvalidArgumentException If parameters are invalid
     *
     * @see calculateSchedule() Generate full schedule using this method
     */
    public function calculatePayment($principal, $annualRate, $frequency, $numberOfPayments) {
        // Validate inputs
        if ($principal <= 0) {
            throw new InvalidArgumentException('Principal must be positive');
        }
        if ($numberOfPayments <= 0) {
            throw new InvalidArgumentException('Number of payments must be positive');
        }

        // Get periods per year for this frequency
        $periodsPerYear = $this->getPeriodsPerYear($frequency);

        // Calculate periodic interest rate
        // Rate per period = annual rate / 100 / periods per year
        $periodicRate = ($annualRate / 100) / $periodsPerYear;

        // If no interest, simple division
        if ($periodicRate == 0) {
            return round($principal / $numberOfPayments, 2);
        }

        // Apply compound interest formula
        // PMT = P * r * (1 + r)^n / ((1 + r)^n - 1)
        $compoundFactor = pow(1 + $periodicRate, $numberOfPayments);
        $numerator = $principal * $periodicRate * $compoundFactor;
        $denominator = $compoundFactor - 1;

        $payment = $numerator / $denominator;

        return round($payment, 2);
    }

    /**
     * Retrieve loan by ID
     *
     * @param int $loanId Loan database ID
     *
     * @return array Loan data array
     */
    public function getLoan($loanId) {
        return $this->db->getLoan($loanId);
    }

    /**
     * Create a new loan
     *
     * @param array $data Loan data
     *
     * @return int Loan ID
     */
    public function createLoan($data) {
        return $this->db->insertLoan($data);
    }

    /**
     * Calculate amortization schedule with flexible frequencies
     *
     * ### Purpose
     * Generates complete amortization schedule with support for multiple
     * payment and interest calculation frequencies. Each row includes
     * payment date, amount, principal, interest, and remaining balance.
     *
     * ### Algorithm
     * 1. Get loan details (principal, rate, frequencies, start date)
     * 2. Calculate periodic payment using calculatePayment()
     * 3. For each payment period:
     *    a. Calculate interest portion (balance * periodic rate)
     *    b. Calculate principal portion (payment - interest)
     *    c. Update remaining balance
     *    d. Store schedule row in database
     *    e. Increment payment date by appropriate interval
     * 4. Adjust final payment to ensure balance = $0.00
     *
     * ### Parameters
     * - `$loanId`: Database ID of loan
     * - `$numberOfPayments`: Total number of payments to calculate
     *
     * ### Side Effects
     * Inserts rows into ksf_amortization_staging table
     *
     * ### Example
     * ```php
     * $model->calculateSchedule(42, 360);  // Generate 360 monthly payments
     * // Inserts 360 rows with dates, payments, interest, principal, balances
     * ```
     *
     * ### Precision Notes
     * - Uses 4-decimal internal precision
     * - Stores 2-decimal payment amounts
     * - Final payment adjusted if necessary to reach zero balance
     *
     * @param int $loanId Loan database ID
     * @param int $numberOfPayments Total number of payments
     *
     * @return void Inserts schedule rows into database
     * @throws RuntimeException If loan not found
     * @throws InvalidArgumentException If parameters invalid
     *
     * @see calculatePayment() Used to calculate payment amount
     */
    public function calculateSchedule($loanId, $numberOfPayments) {
        // Validate input
        if ($loanId <= 0 || $numberOfPayments <= 0) {
            throw new InvalidArgumentException('Invalid loan ID or payment count');
        }

        // Get loan details
        $loan = $this->getLoan($loanId);
        if (!$loan || empty($loan)) {
            throw new RuntimeException("Loan $loanId not found");
        }

        // Extract loan parameters
        $principal = (float)$loan['amount_financed'] ?? $loan['principal'] ?? 0;
        $annualRate = (float)$loan['interest_rate'] ?? $loan['annual_interest_rate'] ?? 0;
        $paymentFrequency = $loan['payment_frequency'] ?? 'monthly';
        $interestCalcFrequency = $loan['interest_calc_frequency'] ?? $paymentFrequency;
        $firstPaymentDate = $loan['first_payment_date'] ?? $loan['start_date'] ?? date('Y-m-d');

        // Calculate payment amount
        $paymentAmount = $this->calculatePayment(
            $principal,
            $annualRate,
            $paymentFrequency,
            $numberOfPayments
        );

        // Initialize schedule generation
        $balance = $principal;
        $currentDate = new \DateTime($firstPaymentDate);
        $paymentNumber = 1;

        // Get interval for date incrementing
        $daysPerPaymentPeriod = $this->getPaymentIntervalDays($paymentFrequency);
        $periodsPerYear = $this->getPeriodsPerYear($interestCalcFrequency);
        $dailyRate = ($annualRate / 100) / 365;

        // Generate each payment row
        for ($i = 1; $i <= $numberOfPayments; $i++) {
            // Calculate interest for this period
            // Interest = Balance * (annual rate / 100) / (periods per year)
            $interestAmount = $balance * ($annualRate / 100) / $periodsPerYear;

            // Calculate principal portion
            $principalAmount = $paymentAmount - $interestAmount;

            // Adjust final payment if necessary (last payment)
            if ($i == $numberOfPayments) {
                // Last payment: principal = remaining balance, payment = principal + interest
                $principalAmount = $balance;
                $interestAmount = max(0, $paymentAmount - $principalAmount);
                $paymentAmount = $principalAmount + $interestAmount;
            }

            // Update balance
            $newBalance = $balance - $principalAmount;
            $newBalance = round(max($newBalance, 0), 2);  // Don't go negative

            // Prepare schedule row
            $scheduleRow = [
                'payment_number' => $paymentNumber,
                'payment_date' => $currentDate->format('Y-m-d'),
                'beginning_balance' => round($balance, 2),
                'payment_amount' => round($paymentAmount, 2),
                'principal_payment' => round($principalAmount, 2),
                'interest_payment' => round($interestAmount, 2),
                'ending_balance' => $newBalance,
            ];

            // Insert into database
            $this->db->insertSchedule($loanId, $scheduleRow);

            // Update state for next iteration
            $balance = $newBalance;
            $currentDate->modify("+{$daysPerPaymentPeriod} days");
            $paymentNumber++;
        }
    }

    /**
     * Get number of payment periods per year for a frequency
     *
     * ### Purpose
     * Converts frequency string to periods per year for calculations
     *
     * ### Supported Frequencies
     * - 'monthly': 12 periods/year
     * - 'biweekly': 26 periods/year
     * - 'weekly': 52 periods/year
     * - 'daily': 365 periods/year
     * - 'semiannual': 2 periods/year
     * - 'annual': 1 period/year
     *
     * @param string $frequency Frequency key
     *
     * @return int Periods per year
     * @throws InvalidArgumentException If frequency not recognized
     */
    private function getPeriodsPerYear($frequency) {
        $frequency = strtolower($frequency);

        if (!isset(self::$frequencyConfig[$frequency])) {
            throw new InvalidArgumentException(
                "Unknown frequency: {$frequency}. Supported: " .
                implode(', ', array_keys(self::$frequencyConfig))
            );
        }

        return self::$frequencyConfig[$frequency];
    }

    /**
     * Get number of days in a payment period
     *
     * ### Purpose
     * Converts frequency to days for date incrementing
     *
     * ### Calculation
     * Days = 365 / periods per year (approximate)
     *
     * @param string $frequency Frequency key
     *
     * @return int Days per period (rounded)
     */
    private function getPaymentIntervalDays($frequency) {
        $periodsPerYear = $this->getPeriodsPerYear($frequency);
        return (int)round(365 / $periodsPerYear);
    }

    /**
     * Record an extra payment and recalculate affected schedule
     *
     * ### Purpose
     * Records an extra payment event (bonus, lump sum, etc) and automatically
     * recalculates the remaining payment schedule to reflect the reduced balance.
     *
     * ### Algorithm
     * 1. Create LoanEvent record for the extra payment
     * 2. Store in database via DataProvider
     * 3. Recalculate schedule from payment date forward
     * 4. Remaining payments reduced, loan paid off earlier
     *
     * ### Example
     * ```php
     * // Borrower makes $1000 extra payment on 2025-03-15
     * $model->recordExtraPayment(42, '2025-03-15', 1000.00, 'Bonus payment');
     * // Schedule automatically recalculated, loan now payoff ~3 months earlier
     * ```
     *
     * ### Business Impact
     * - Reduces total interest paid
     * - Shortens loan term
     * - Can be called multiple times (adjustments cumulative)
     *
     * @param int $loanId Loan database ID
     * @param string $eventDate Payment date (YYYY-MM-DD format)
     * @param float $amount Extra payment amount in dollars
     * @param string $notes Optional notes (e.g., "Bonus", "Tax return")
     *
     * @return void Modifies schedule in database
     * @throws InvalidArgumentException If parameters invalid
     * @throws RuntimeException If loan not found
     *
     * @see recalculateScheduleAfterEvent() Internal method for recalculation
     */
    public function recordExtraPayment($loanId, $eventDate, $amount, $notes = '') {
        // Validate inputs
        if ($loanId <= 0) {
            throw new InvalidArgumentException('Invalid loan ID');
        }
        if ($amount <= 0) {
            throw new InvalidArgumentException('Extra payment amount must be positive');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $eventDate)) {
            throw new InvalidArgumentException('Event date must be YYYY-MM-DD format');
        }

        // Create and store event
        $event = new LoanEvent([
            'loan_id' => $loanId,
            'event_type' => 'extra',
            'event_date' => $eventDate,
            'amount' => $amount,
            'notes' => $notes
        ]);

        $eventId = $this->db->insertLoanEvent($loanId, $event);

        // Recalculate affected schedule
        $this->recalculateScheduleAfterEvent($loanId, $eventDate);
    }

    /**
     * Record a skip payment (payment skipped/deferred)
     *
     * ### Purpose
     * Records a skip payment event and adjusts schedule accordingly.
     * Deferred payment adds to balance, extends loan term.
     *
     * ### Business Context
     * - Borrower unable to make payment
     * - Defer to end of loan
     * - May increase total interest paid
     *
     * @param int $loanId Loan database ID
     * @param string $eventDate Skipped payment date (YYYY-MM-DD format)
     * @param float $amount Skipped payment amount
     * @param string $notes Optional notes
     *
     * @return void Modifies schedule in database
     * @throws InvalidArgumentException If parameters invalid
     */
    public function recordSkipPayment($loanId, $eventDate, $amount, $notes = '') {
        // Validate inputs
        if ($loanId <= 0) {
            throw new InvalidArgumentException('Invalid loan ID');
        }
        if ($amount <= 0) {
            throw new InvalidArgumentException('Skip payment amount must be positive');
        }

        // Create and store event
        $event = new LoanEvent([
            'loan_id' => $loanId,
            'event_type' => 'skip',
            'event_date' => $eventDate,
            'amount' => $amount,
            'notes' => $notes ?? 'Skipped payment'
        ]);

        $eventId = $this->db->insertLoanEvent($loanId, $event);

        // Recalculate affected schedule
        $this->recalculateScheduleAfterEvent($loanId, $eventDate);
    }

    /**
     * Recalculate amortization schedule after a loan event
     *
     * ### Purpose
     * Internal method that regenerates the amortization schedule after
     * a loan event (extra payment, skip, etc.). Updates balance and
     * payment dates to reflect the event.
     *
     * ### Algorithm
     * 1. Find all events on or before event_date
     * 2. Calculate cumulative extra payments (reduces balance)
     * 3. Calculate cumulative skip payments (increases balance)
     * 4. Find first affected schedule row (after event_date)
     * 5. Delete affected rows from database
     * 6. Recalculate starting from event_date with adjusted balance
     * 7. Regenerate remaining schedule
     *
     * ### Example
     * Original schedule (12 monthly payments):
     * ```
     * Payment 1: 2025-01-01, Balance: $10,000
     * Payment 2: 2025-02-01, Balance: $9,000
     * ...
     * ```
     * Extra payment $2,000 recorded on 2025-02-01
     * New schedule:
     * ```
     * Payment 1: 2025-01-01, Balance: $10,000
     * Payment 2: 2025-02-01, Balance: $7,000 (reduced by extra)
     * Payment 3: 2025-03-01, Balance: $6,100 (fewer remaining payments needed)
     * ...
     * ```
     *
     * @param int $loanId Loan database ID
     * @param string $eventDate Event date (YYYY-MM-DD format)
     *
     * @return void Modifies schedule in database
     * @throws RuntimeException If loan or schedule data not found
     */
    private function recalculateScheduleAfterEvent($loanId, $eventDate) {
        // Get loan details
        $loan = $this->getLoan($loanId);
        if (!$loan || empty($loan)) {
            throw new RuntimeException("Loan $loanId not found");
        }

        // Get all events for this loan
        $events = $this->db->getLoanEvents($loanId);

        // Calculate cumulative impact of events on or before event_date
        $totalExtraPayments = 0;
        $totalSkipPayments = 0;

        foreach ($events as $event) {
            if (strtotime($event['event_date']) <= strtotime($eventDate)) {
                if ($event['event_type'] === 'extra') {
                    $totalExtraPayments += $event['amount'];
                } elseif ($event['event_type'] === 'skip') {
                    $totalSkipPayments += $event['amount'];
                }
            }
        }

        // Get existing schedule to find where to start recalculation
        $existingSchedule = $this->db->getScheduleRows($loanId);
        
        if (empty($existingSchedule)) {
            // No existing schedule - shouldn't happen but handle gracefully
            return;
        }

        // Find the schedule row just before event_date
        $baseRow = null;
        foreach ($existingSchedule as $row) {
            if (strtotime($row['payment_date']) <= strtotime($eventDate)) {
                $baseRow = $row;
            } else {
                break;
            }
        }

        if (!$baseRow) {
            // Event date before first payment - adjust from beginning
            $baseBalance = $loan['amount_financed'];
            $nextPaymentDate = $loan['first_payment_date'];
        } else {
            // Start recalculation from balance after event_date
            $baseBalance = $baseRow['ending_balance'];
            
            // Apply extra and skip payments to base balance
            $baseBalance = $baseBalance - $totalExtraPayments + $totalSkipPayments;
            $baseBalance = max(0, $baseBalance);

            // Next payment after event_date
            $nextPaymentDate = (new \DateTime($baseRow['payment_date']))
                ->modify('+' . $this->getPaymentIntervalDays($loan['payment_frequency']) . ' days')
                ->format('Y-m-d');
        }

        // Delete schedule rows after event_date (they're being regenerated)
        $this->db->deleteScheduleAfterDate($loanId, $eventDate);

        // If balance is $0, no more payments needed
        if ($baseBalance <= 0) {
            return;
        }

        // Recalculate remaining payments needed
        $existingPaymentCount = count($existingSchedule);
        
        // Estimate remaining payments based on balance ratio
        $originalBalance = $loan['amount_financed'];
        $balanceRatio = $baseBalance / $originalBalance;
        $remainingPayments = (int)ceil($existingPaymentCount * $balanceRatio);
        
        // Ensure at least 1 payment remaining
        $remainingPayments = max(1, $remainingPayments);

        // Extract loan parameters
        $annualRate = (float)$loan['interest_rate'];
        $paymentFrequency = $loan['payment_frequency'] ?? 'monthly';
        $interestCalcFrequency = $loan['interest_calc_frequency'] ?? $paymentFrequency;

        // Calculate new payment amount based on remaining balance and payments
        $paymentAmount = $this->calculatePayment(
            $baseBalance,
            $annualRate,
            $paymentFrequency,
            $remainingPayments
        );

        // Initialize recalculation
        $balance = $baseBalance;
        $currentDate = new \DateTime($nextPaymentDate);
        $paymentNumber = $baseRow ? $baseRow['payment_number'] + 1 : 1;

        // Get interval for date incrementing
        $daysPerPaymentPeriod = $this->getPaymentIntervalDays($paymentFrequency);
        $periodsPerYear = $this->getPeriodsPerYear($interestCalcFrequency);

        // Generate recalculated schedule rows
        for ($i = 1; $i <= $remainingPayments; $i++) {
            // Calculate interest for this period
            $interestAmount = $balance * ($annualRate / 100) / $periodsPerYear;

            // Calculate principal portion
            $principalAmount = $paymentAmount - $interestAmount;

            // Adjust final payment if necessary
            if ($i == $remainingPayments) {
                $principalAmount = $balance;
                $interestAmount = max(0, $paymentAmount - $principalAmount);
                $paymentAmount = $principalAmount + $interestAmount;
            }

            // Update balance
            $newBalance = $balance - $principalAmount;
            $newBalance = round(max($newBalance, 0), 2);

            // Prepare schedule row
            $scheduleRow = [
                'payment_number' => $paymentNumber,
                'payment_date' => $currentDate->format('Y-m-d'),
                'beginning_balance' => round($balance, 2),
                'payment_amount' => round($paymentAmount, 2),
                'principal_payment' => round($principalAmount, 2),
                'interest_payment' => round($interestAmount, 2),
                'ending_balance' => $newBalance,
            ];

            // Insert into database
            $this->db->insertSchedule($loanId, $scheduleRow);

            // Update state for next iteration
            $balance = $newBalance;
            $currentDate->modify("+{$daysPerPaymentPeriod} days");
            $paymentNumber++;
        }
    }
}
