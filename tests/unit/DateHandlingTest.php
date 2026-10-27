<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class DateHandlingTest extends CIUnitTestCase
{
    public function testDisplayDateIsNormalizedToIso(): void
    {
        $this->assertSame('2024-02-29', usha_derma_parse_date('29-02-2024'));
        $this->assertSame('29-02-2024', usha_derma_format_date('2024-02-29'));
    }

    public function testIsoInputIsAcceptedWithoutDoubleConversion(): void
    {
        $this->assertSame('2024-02-29', usha_derma_parse_date('2024-02-29'));
        $this->assertSame('29-02-2024', usha_derma_format_date('29-02-2024'));
    }

    public function testInvalidDatesAndLeapYearsAreRejected(): void
    {
        $this->assertNull(usha_derma_parse_date('31-02-2024'));
        $this->assertNull(usha_derma_parse_date('29-02-2023'));
        $this->assertSame('2024-02-29', usha_derma_parse_date('29-02-2024'));
    }

    public function testEmptyOptionalDatesRemainEmpty(): void
    {
        $this->assertNull(usha_derma_parse_date(''));
        $this->assertSame('', usha_derma_format_date(null));
    }
}
