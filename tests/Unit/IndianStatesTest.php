<?php

namespace Tests\Unit;

use App\Support\IndianStates;
use PHPUnit\Framework\TestCase;

class IndianStatesTest extends TestCase
{
    public function test_a_state_is_read_however_it_is_typed(): void
    {
        foreach ([
            'Punjab' => '03', ' punjab. ' => '03', 'PB' => '03', '03' => '03',
            'Maharashtra' => '27', 'MH' => '27',
            'Jammu & Kashmir' => '01', 'J&K' => '01',
            'New Delhi' => '07', 'NCT of Delhi' => '07',
            'Orissa' => '21', 'Pondicherry' => '34', 'Telangana' => '36', 'TS' => '36',
            'Andaman & Nicobar Islands' => '35',
        ] as $typed => $code) {
            $this->assertSame($code, IndianStates::code($typed), $typed);
        }

        foreach (['', null, 'Somewhere', '25', '99'] as $typed) {
            $this->assertNull(IndianStates::code($typed), var_export($typed, true));
        }
    }

    public function test_the_gstin_gives_the_state_and_india_is_recognised(): void
    {
        $this->assertSame('03', IndianStates::codeFromGstin('03AAAAA0000A1Z5'));
        $this->assertNull(IndianStates::codeFromGstin('99AAAAA0000A1Z5'));
        $this->assertNull(IndianStates::codeFromGstin(null));
        $this->assertSame('Punjab (03)', IndianStates::label('03'));

        $this->assertTrue(IndianStates::isIndia('India'));
        $this->assertTrue(IndianStates::isIndia(' IN '));
        $this->assertFalse(IndianStates::isIndia('Canada'));
    }
}
