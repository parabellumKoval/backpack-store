<?php

namespace Backpack\Store\Tests\Unit;

use Backpack\Store\app\Console\Commands\XmlSource;
use PHPUnit\Framework\TestCase;

class XmlSourceRuleMatchingTest extends TestCase
{
    private function searchInArray($search, array $rules): bool
    {
        $reflection = new \ReflectionClass(XmlSource::class);
        $command = $reflection->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod($command, 'searchInArray');
        $method->setAccessible(true);

        return $method->invoke($command, $search, $rules);
    }

    /** @test */
    public function it_matches_exact_values_case_insensitively_for_cyrillic_and_special_symbols()
    {
        $this->assertTrue($this->searchInArray('Уцінка', ['уцінка']));
        $this->assertTrue($this->searchInArray('C++', ['c++']));
        $this->assertTrue($this->searchInArray('(Test)', ['(test)']));
    }

    /** @test */
    public function it_matches_percent_mask_case_insensitively()
    {
        $this->assertTrue($this->searchInArray('Рукавички для тренувань', ['%ТРЕНУВАНЬ%']));
        $this->assertFalse($this->searchInArray('Рукавички для боксу', ['%ТРЕНУВАНЬ%']));
    }

    /** @test */
    public function it_matches_starts_with_mask_case_insensitively()
    {
        $this->assertTrue($this->searchInArray('Одяг для спорту', ['^одяг']));
        $this->assertFalse($this->searchInArray('Спортивний одяг', ['^одяг']));
    }
}
