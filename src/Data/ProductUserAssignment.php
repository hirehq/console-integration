<?php

namespace HireHq\ConsoleIntegration\Data;

final readonly class ProductUserAssignment
{
    /** Staff status must come from the authenticated Console payload, never an email-domain inference. */
    public function __construct(
        public ProductContext $context,
        public string $membershipId,
        public string $workosUserId,
        public string $email,
        public string $role,
        public int $revision,
        public bool $isHireHqStaff = false,
    ) {}
}
