<?php

namespace HireHq\ConsoleIntegration\Contracts;

use HireHq\ConsoleIntegration\Data\CompanyProvisioningRequest;

interface CompanyProvisioner
{
    /** Idempotently provision and return the stable product-local tenant ID. Entitlement alone is not readiness. */
    public function provision(CompanyProvisioningRequest $request): string;

    /** Explicitly link a verified existing tenant; reject conflicting mappings and preserve operational records. */
    public function linkExisting(CompanyProvisioningRequest $request, string $localTenantId): string;
}
