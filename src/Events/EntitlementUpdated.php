<?php

namespace HireHq\ConsoleIntegration\Events;

use HireHq\ConsoleIntegration\Data\EntitlementSnapshot;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

final readonly class EntitlementUpdated implements ShouldDispatchAfterCommit
{
    public function __construct(public EntitlementSnapshot $snapshot) {}
}
