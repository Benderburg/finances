<?php

namespace Tests\Unit;

use App\Domain\DomainError;
use App\Domain\Money;
use App\Migration\ExactJson;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_exact_decimals_limit_and_half_up(): void
    {
        $this->assertSame('100025', Money::decimal('1000.2500'));
        $this->assertSame('3333', Money::target('10000', '3'));
        $this->assertSame('1', Money::target('1', '2'));
        $this->assertSame('1999999999999998', Money::sum([Money::MAX, Money::MAX]));
        $this->assertSame('9999999999999.99', ExactJson::decode('{"amount":9999999999999.99}')['amount']);
        $this->expectException(DomainError::class);
        Money::decimal('0.001');
    }
}
