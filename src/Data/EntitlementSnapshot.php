<?php

namespace HireHq\ConsoleIntegration\Data;

final readonly class EntitlementSnapshot
{
    public function __construct(
        public ProductContext $context,
        public int $revision,
        public string $status,
        public int $validUntil,
        public ?int $trialEndsAt = null,
        public ?int $accessEndsAt = null,
    ) {}

    public function allowsAccess(int $now): bool
    {
        if ($this->validUntil <= $now || ($this->accessEndsAt !== null && $this->accessEndsAt <= $now)) {
            return false;
        }

        return match ($this->status) {
            'active' => true,
            'trialing' => $this->trialEndsAt !== null && $this->trialEndsAt > $now,
            default => false,
        };
    }

    /** @return array{environment: string, product: string, company_id: string, revision: int, status: string, valid_until: int, trial_ends_at: ?int, access_ends_at: ?int} */
    public function toArray(): array
    {
        return [...$this->context->key(), 'revision' => $this->revision, 'status' => $this->status,
            'valid_until' => $this->validUntil, 'trial_ends_at' => $this->trialEndsAt, 'access_ends_at' => $this->accessEndsAt];
    }
}
