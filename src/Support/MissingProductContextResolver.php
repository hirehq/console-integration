<?php

namespace HireHq\ConsoleIntegration\Support;

use HireHq\ConsoleIntegration\Contracts\ProductContextResolver;
use HireHq\ConsoleIntegration\Data\ProductContext;
use Illuminate\Http\Request;

final class MissingProductContextResolver implements ProductContextResolver
{
    public function resolve(Request $request): ?ProductContext
    {
        return null;
    }
}
