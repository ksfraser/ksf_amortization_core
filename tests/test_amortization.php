<?php
/**
 * Unit tests for AmortizationModel
 * 
 * @package AmortizationModule
 * @author ksfraser
 * @covers AmortizationModel
 */
use PHPUnit\Framework\TestCase;

class AmortizationModelTest extends TestCase
{
    /**
     * @var PDO
     */
    private $db;

    /**
     * @var AmortizationModel
     */
    private $model;

    protected function setUp(): void
    {
        // Use in-memory SQLite for testing
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->exec(file_get_contents(__DIR__ . '/../schema.sql'));
        $this->model = new AmortizationModel($this->db);
    }

    /**
     * Test loan creation
     */
    public function testCreateLoan()
    {
        $data = [
            'loan_type' => 'Auto',
            'description' => 'Test Auto Loan',
            'principal' => 10000,
            'interest_rate' => 5.0,
            'term_months' => 12,
            'repayment_schedule' => 'monthly',
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
            'created_by' => 1
        ];
        $loan_id = $this->model->createLoan($data);
        $this->assertIsInt($loan_id);
        $loan = $this->model->getLoan($loan_id);
        $this->assertEquals('Auto', $loan['loan_type']);
        $this->assertEquals(10000, $loan['principal']);
    }

    /**
     * Test amortization schedule calculation
     */
    public function testCalculateSchedule()
    {
        $data = [
            'loan_type' => 'Mortgage',
            'description' => 'Test Mortgage',
            'principal' => 120000,
            'interest_rate' => 4.5,
            'term_months' => 24,
            'repayment_schedule' => 'monthly',
            'start_date' => '2025-01-01',
            'end_date' => '2026-12-31',
            'created_by' => 1
        ];
        $loan_id = $this->model->createLoan($data);
        $this->model->calculateSchedule($loan_id);
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM fa_amortization_staging WHERE loan_id = ?');
        $stmt->execute([$loan_id]);
        $count = $stmt->fetchColumn();
        $this->assertEquals(24, $count);
    }
}
