<?php

namespace App\Tenancy;

use App\Enums\FirmMembershipStatus;
use App\Enums\FirmStatus;
use App\Models\Firm;
use App\Models\FirmMembership;
use Closure;
use LogicException;

class FirmContext
{
    private ?Firm $firm = null;

    private ?FirmMembership $membership = null;

    public function activateMembership(FirmMembership $membership): void
    {
        $firm = $membership->relationLoaded('firm')
            ? $membership->firm
            : $membership->firm()->firstOrFail();

        if ($membership->status !== FirmMembershipStatus::Active) {
            throw new LogicException('The firm membership is not active.');
        }

        $this->assertActiveFirm($firm);

        $this->firm = $firm;
        $this->membership = $membership;
    }

    /**
     * Run trusted system work in one firm and restore the previous context.
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult
     */
    public function runForFirm(Firm $firm, Closure $callback): mixed
    {
        $this->assertActiveFirm($firm);

        $previousFirm = $this->firm;
        $previousMembership = $this->membership;

        $this->firm = $firm;
        $this->membership = null;

        try {
            return $callback();
        } finally {
            $this->firm = $previousFirm;
            $this->membership = $previousMembership;
        }
    }

    public function clear(): void
    {
        $this->firm = null;
        $this->membership = null;
    }

    public function hasFirm(): bool
    {
        return $this->firm !== null;
    }

    public function firm(): Firm
    {
        return $this->firm
            ?? throw new LogicException('An active firm context is required.');
    }

    public function firmId(): ?string
    {
        return $this->firm?->getKey();
    }

    public function membership(): ?FirmMembership
    {
        return $this->membership;
    }

    private function assertActiveFirm(Firm $firm): void
    {
        if ($firm->status !== FirmStatus::Active) {
            throw new LogicException('The firm is not active.');
        }
    }
}
