<?php

namespace eiriksm\CosyComposerTest\unit;

use eiriksm\CosyComposer\UpdateRequestExpirationPolicy;
use PHPUnit\Framework\TestCase;

class UpdateRequestExpirationPolicyTest extends TestCase
{
    public function testRequestExpiresAtConfiguredAge() : void
    {
        $policy = new UpdateRequestExpirationPolicy('8w', '16w');
        $request = [
            'created_at' => '2026-01-01T00:00:00+00:00',
        ];

        self::assertFalse($policy->isExpired($request, new \DateTimeImmutable('2026-02-25T23:59:59+00:00')));
        self::assertTrue($policy->isExpired($request, new \DateTimeImmutable('2026-02-26T00:00:00+00:00')));
    }

    public function testCooldownStartsWhenExpiredRequestWasClosed() : void
    {
        $policy = new UpdateRequestExpirationPolicy('8w', '16w');
        $request = [
            'created_at' => '2026-01-01T00:00:00+00:00',
            'closed_at' => '2026-02-26T00:00:00+00:00',
        ];

        self::assertTrue($policy->isInCooldown($request, new \DateTimeImmutable('2026-06-17T23:59:59+00:00')));
        self::assertFalse($policy->isInCooldown($request, new \DateTimeImmutable('2026-06-18T00:00:00+00:00')));
    }

    public function testRequestClosedBeforeMaximumAgeDoesNotStartCooldown() : void
    {
        $policy = new UpdateRequestExpirationPolicy('8w', '16w');
        $request = [
            'created_at' => '2026-01-01T00:00:00+00:00',
            'closed_at' => '2026-01-15T00:00:00+00:00',
        ];

        self::assertFalse($policy->isInCooldown($request, new \DateTimeImmutable('2026-01-20T00:00:00+00:00')));
    }

    public function testMonthsUseCalendarArithmetic() : void
    {
        $policy = new UpdateRequestExpirationPolicy('1m', '2m');
        $request = [
            'created_at' => '2026-01-15T00:00:00+00:00',
            'closed_at' => '2026-02-15T00:00:00+00:00',
        ];

        self::assertTrue($policy->isInCooldown($request, new \DateTimeImmutable('2026-04-14T23:59:59+00:00')));
        self::assertFalse($policy->isInCooldown($request, new \DateTimeImmutable('2026-04-15T00:00:00+00:00')));
    }

    public function testMissingOrInvalidDatesAreIgnored() : void
    {
        $policy = new UpdateRequestExpirationPolicy('8w', '16w');

        self::assertFalse($policy->isExpired([], new \DateTimeImmutable('2026-01-01T00:00:00+00:00')));
        self::assertFalse($policy->isExpired(['created_at' => 'not-a-date'], new \DateTimeImmutable('2026-01-01T00:00:00+00:00')));
        self::assertFalse($policy->isInCooldown(['created_at' => '2026-01-01T00:00:00+00:00'], new \DateTimeImmutable('2026-01-01T00:00:00+00:00')));
    }
}
