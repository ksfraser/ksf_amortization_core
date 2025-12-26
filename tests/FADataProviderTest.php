<?php
namespace Ksfraser\Amortizations\Tests;

use Ksfraser\Amortizations\FA\FADataProvider;
use PHPUnit\Framework\TestCase;

class FADataProviderTest extends TestCase
{
    private $pdoMock;
    private $provider;

    protected function setUp(): void
    {
        // Create a mock PDO object
        $this->pdoMock = $this->getMockBuilder(\PDO::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->provider = new FADataProvider($this->pdoMock);
    }

    public function testGetLoanReturnsExpectedArray()
    {
        // Mock the PDOStatement
        $stmtMock = $this->getMockBuilder(\PDOStatement::class)
            ->disableOriginalConstructor()
            ->getMock();
        $stmtMock->expects($this->once())
            ->method('fetch')
            ->willReturn([
                'id' => 1,
                'principal' => 1000,
                'interest_rate' => 5.0,
                'term' => 12,
                'schedule' => 'monthly'
            ]);
        $this->pdoMock->expects($this->once())
            ->method('prepare')
            ->willReturn($stmtMock);
        $stmtMock->expects($this->once())
            ->method('execute')
            ->with([1]);

        $result = $this->provider->getLoan(1);
        $this->assertEquals(1000, $result['principal']);
        $this->assertEquals(5.0, $result['interest_rate']);
        $this->assertEquals(12, $result['term']);
        $this->assertEquals('monthly', $result['schedule']);
    }

    public function testInsertLoanReturnsId()
    {
        $stmtMock = $this->getMockBuilder(\PDOStatement::class)
            ->disableOriginalConstructor()
            ->getMock();
        $stmtMock->expects($this->once())
            ->method('execute')
            ->willReturn(true);
        $this->pdoMock->expects($this->once())
            ->method('prepare')
            ->willReturn($stmtMock);
        $this->pdoMock->expects($this->once())
            ->method('lastInsertId')
            ->willReturn(42);

        $data = ['principal' => 1000, 'interest_rate' => 5.0, 'term' => 12, 'schedule' => 'monthly'];
        $result = $this->provider->insertLoan($data);
        $this->assertEquals(42, $result);
    }

    public function testInsertScheduleNoException()
    {
        $stmtMock = $this->getMockBuilder(\PDOStatement::class)
            ->disableOriginalConstructor()
            ->getMock();
        $stmtMock->expects($this->once())
            ->method('execute')
            ->willReturn(true);
        $this->pdoMock->expects($this->once())
            ->method('prepare')
            ->willReturn($stmtMock);

        $schedule_row = [
            'payment_date' => '2025-08-01',
            'payment_amount' => 100.0,
            'principal_portion' => 80.0,
            'interest_portion' => 20.0,
            'remaining_balance' => 900.0
        ];
        $this->provider->insertSchedule(1, $schedule_row);
        $this->assertTrue(true); // If no exception, test passes
    }
}
