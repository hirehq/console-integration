<?php

namespace HireHq\ConsoleIntegration\Events;

use HireHq\ConsoleIntegration\Data\ProductContext;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

final readonly class ErpConnectionChanged implements ShouldDispatchAfterCommit
{
    /** Only references are event-safe; retrieve credentials through the host application's secret provider. */
    public function __construct(public ProductContext $context, public string $connectionReference, public int $version) {}
}
