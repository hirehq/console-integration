<?php

namespace HireHq\ConsoleIntegration\Data;

final readonly class CompanyProvisioningRequest
{
    /**
     * @param  array{id: string, version: string, host: string, port: int, database: string, secret_reference: string}  $connection
     * @param  list<array{membership_id: string, workos_user_id: string, email: string, role: string}>  $users
     */
    public function __construct(
        public ProductContext $context,
        public string $idempotencyKey,
        public string $name,
        public string $workosOrganisationId,
        public array $connection,
        public array $users,
        public int $revision,
    ) {}
}
