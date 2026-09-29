<?php

namespace HireHq\ConsoleIntegration\Events;

use HireHq\ConsoleIntegration\Data\ProductUserAssignment;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

final readonly class ProductUserAssigned implements ShouldDispatchAfterCommit
{
    public function __construct(public ProductUserAssignment $assignment) {}
}
