<?php
use PHPUnit\Framework\TestCase;
use Ksfraser\Amortizations\LoanType;

class LoanTypeTest extends TestCase {
    public function testConstructWithData() {
        $data = [
            'id' => 1,
            'name' => 'Auto',
            'description' => 'Auto loan type',
        ];
        $type = new LoanType($data);
        $this->assertEquals(1, $type->id);
        $this->assertEquals('Auto', $type->name);
        $this->assertEquals('Auto loan type', $type->description);
    }

    public function testConstructWithDefaults() {
        $type = new LoanType();
        $this->assertNull($type->id);
        $this->assertEquals('', $type->name);
        $this->assertEquals('', $type->description);
    }
}
