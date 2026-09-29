<?php

namespace HireHq\ConsoleIntegration\Events;

use HireHq\ConsoleIntegration\Data\ProductContext;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

final readonly class ProductProvisioned implements ShouldDispatchAfterCommit
{
    public function __construct(public ProductContext $context, public string $localTenantId) {}
}
