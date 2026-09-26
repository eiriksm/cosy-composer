<?php

namespace eiriksm\CosyComposer;

class UpdateRequestExpirationPolicy
{
    private $maximumAge;
    private $cooldown;

    public function __construct(string $maximum_age, string $cooldown)
    {
        $this->maximumAge = $maximum_age;
        $this->cooldown = $cooldown;
    }

    public function isExpired(array $request, ?\DateTimeImmutable $now = null) : bool
    {
        if (empty($request['created_at'])) {
            return false;
        }
        try {
            $created_at = new \DateTimeImmutable($request['created_at']);
        } catch (\Throwable $e) {
            return false;
        }
        $expires_at = $this->modifyByDuration($created_at, $this->maximumAge, 1);
        if (!$expires_at) {
            return false;
        }
        $now = $now ?: new \DateTimeImmutable('now');
        return $expires_at <= $now;
    }

    public function isInCooldown(array $request, ?\DateTimeImmutable $now = null) : bool
    {
        if (empty($request['created_at']) || empty($request['closed_at'])) {
            return false;
        }
        try {
            $created_at = new \DateTimeImmutable($request['created_at']);
            $closed_at = new \DateTimeImmutable($request['closed_at']);
        } catch (\Throwable $e) {
            return false;
        }
        $expired_at = $this->modifyByDuration($created_at, $this->maximumAge, 1);
        if (!$expired_at || $closed_at < $expired_at) {
            return false;
        }
        $cooldown_until = $this->modifyByDuration($closed_at, $this->cooldown, 1);
        if (!$cooldown_until) {
            return false;
        }
        $now = $now ?: new \DateTimeImmutable('now');
        return $cooldown_until > $now;
    }

    private function modifyByDuration(\DateTimeImmutable $date, string $duration, int $direction) : ?\DateTimeImmutable
    {
        if (!preg_match('/^([1-9][0-9]*)([dwm])$/', $duration, $matches)) {
            return null;
        }
        $units = [
            'd' => 'days',
            'w' => 'weeks',
            'm' => 'months',
        ];
        $prefix = $direction < 0 ? '-' : '+';
        $modified = $date->modify(sprintf('%s%d %s', $prefix, (int) $matches[1], $units[$matches[2]]));
        return $modified ?: null;
    }
}
