<?php
use PHPUnit\Framework\TestCase;
use Ksfraser\Amortizations\InterestCalcFrequency;

class InterestCalcFrequencyTest extends TestCase {
    public function testConstructWithData() {
        $data = [
            'id' => 2,
            'name' => 'Monthly',
            'description' => 'Monthly interest calculation',
        ];
        $freq = new InterestCalcFrequency($data);
        $this->assertEquals(2, $freq->id);
        $this->assertEquals('Monthly', $freq->name);
        $this->assertEquals('Monthly interest calculation', $freq->description);
    }

    public function testConstructWithDefaults() {
        $freq = new InterestCalcFrequency();
        $this->assertNull($freq->id);
        $this->assertEquals('', $freq->name);
        $this->assertEquals('', $freq->description);
    }
}
