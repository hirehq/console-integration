<?php

namespace HireHq\ConsoleIntegration\Contracts;

use HireHq\ConsoleIntegration\Data\ProductContext;
use HireHq\ConsoleIntegration\Data\ProductUserAssignment;

interface ProductUserManager
{
    /** Idempotent by membership/revision. Must reject unknown roles and non-staff superuser assignments. */
    public function assign(ProductUserAssignment $assignment): void;

    /** Revoke sessions/tokens and derived access too; preserve a revision tombstone to reject stale grants. */
    public function revoke(ProductContext $context, string $membershipId, int $revision): void;
}
