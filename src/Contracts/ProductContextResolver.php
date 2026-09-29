<?php

namespace HireHq\ConsoleIntegration\Contracts;

use HireHq\ConsoleIntegration\Data\ProductContext;
use Illuminate\Http\Request;

interface ProductContextResolver
{
    /** Resolve only after the host has authenticated the user and authorised their tenant membership. */
    public function resolve(Request $request): ?ProductContext;
}
