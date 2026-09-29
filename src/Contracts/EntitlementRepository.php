<?php

namespace HireHq\ConsoleIntegration\Contracts;

use HireHq\ConsoleIntegration\Data\EntitlementSnapshot;
use HireHq\ConsoleIntegration\Data\ProductContext;

interface EntitlementRepository
{
    public function find(ProductContext $context): ?EntitlementSnapshot;

    /** Return false for an identical retry; conflicting or older revisions throw RevisionConflict. */
    public function store(EntitlementSnapshot $snapshot): bool;
}
