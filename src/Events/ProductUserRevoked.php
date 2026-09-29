<?php

namespace HireHq\ConsoleIntegration\Events;

use HireHq\ConsoleIntegration\Data\ProductContext;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

final readonly class ProductUserRevoked implements ShouldDispatchAfterCommit
{
    public function __construct(public ProductContext $context, public string $membershipId, public int $revision) {}
}
