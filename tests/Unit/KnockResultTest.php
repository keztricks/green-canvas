<?php

namespace Tests\Unit;

use App\Models\KnockResult;
use Tests\TestCase;

class KnockResultTest extends TestCase
{
    public function test_response_options_returns_array_with_expected_keys(): void
    {
        $options = KnockResult::responseOptions();

        $this->assertIsArray($options);
        $this->assertArrayHasKey('not_home', $options);
        $this->assertArrayHasKey('undecided', $options);
        $this->assertArrayHasKey('refused', $options);
        $this->assertArrayHasKey('wont_vote', $options);
        $this->assertArrayHasKey('other', $options);
    }

    public function test_turnout_likelihood_options_returns_expected_keys(): void
    {
        $options = KnockResult::turnoutLikelihoodOptions();

        $this->assertIsArray($options);
        $this->assertSame(['wont', 'might', 'will'], array_keys($options));
    }
}
