<?php

declare(strict_types=1);

namespace Flowy\Tests\Model\Data;

use Flowy\Model\Data\RetryPolicy;
use PHPUnit\Framework\TestCase;

class RetryPolicyTest extends TestCase
{
    public function testConstructWithDefaults(): void
    {
        $policy = new RetryPolicy();
        $this->assertSame(0, $policy->attempts);
        $this->assertSame(0, $policy->fixedDelaySeconds);
        $this->assertSame(0, $policy->exponentialBackoffSeconds);
        $this->assertSame(0.0, $policy->jitterFactor);
        $this->assertSame(0, $policy->maxDelaySeconds);
    }

    public function testConstructWithValidValues(): void
    {
        $policy = new RetryPolicy(3, 10, 5, 0.5, 60);
        $this->assertSame(3, $policy->attempts);
        $this->assertSame(10, $policy->fixedDelaySeconds);
        $this->assertSame(5, $policy->exponentialBackoffSeconds);
        $this->assertSame(0.5, $policy->jitterFactor);
        $this->assertSame(60, $policy->maxDelaySeconds);
    }

    public function testNegativeAttemptsThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RetryPolicy(-1);
    }

    public function testNegativeFixedDelayThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RetryPolicy(1, -5);
    }

    public function testNegativeExponentialBackoffThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RetryPolicy(1, 0, -2);
    }

    public function testJitterFactorBelowZeroThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RetryPolicy(1, 0, 0, -0.1);
    }

    public function testJitterFactorAboveOneThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RetryPolicy(1, 0, 0, 1.1);
    }

    public function testNegativeMaxDelayThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RetryPolicy(1, 0, 0, 0.0, -10);
    }

    public function testGetDelayForAttemptWithFixedDelay(): void
    {
        $policy = new RetryPolicy(3, 10);
        $this->assertSame(10, $policy->getDelayForAttempt(1));
        $this->assertSame(10, $policy->getDelayForAttempt(2));
        $this->assertSame(10, $policy->getDelayForAttempt(3));
    }

    public function testGetDelayForAttemptWithExponentialBackoff(): void
    {
        $policy = new RetryPolicy(3, 0, 5);
        $this->assertSame(5, $policy->getDelayForAttempt(1));
        $this->assertSame(10, $policy->getDelayForAttempt(2));
        $this->assertSame(20, $policy->getDelayForAttempt(3));
    }

    public function testGetDelayForAttemptWithMaxDelay(): void
    {
        $policy = new RetryPolicy(3, 0, 5, 0.0, 12);
        $this->assertSame(5, $policy->getDelayForAttempt(1));
        $this->assertSame(10, $policy->getDelayForAttempt(2));
        $this->assertSame(12, $policy->getDelayForAttempt(3)); // 20 > 12, so capped
    }

    public function testGetDelayForAttemptWithJitter(): void
    {
        $policy = new RetryPolicy(3, 10, 0, 0.5);
        $delay = $policy->getDelayForAttempt(1);
        $this->assertGreaterThanOrEqual(10, $delay);
        $this->assertLessThanOrEqual(15, $delay); // 10 + 10*0.5 = 15 max
    }

    public function testGetDelayForAttemptWithAllOptions(): void
    {
        $policy = new RetryPolicy(3, 0, 5, 0.5, 12);
        $delay = $policy->getDelayForAttempt(3); // base = 20, capped to 12, jitter up to 12+6=18
        $this->assertGreaterThanOrEqual(12, $delay);
        $this->assertLessThanOrEqual(18, $delay);
    }

    public function testGetDelayForAttemptThrowsOnInvalidAttempt(): void
    {
        $policy = new RetryPolicy(3, 10);
        $this->expectException(\InvalidArgumentException::class);
        $policy->getDelayForAttempt(0);
    }
}
