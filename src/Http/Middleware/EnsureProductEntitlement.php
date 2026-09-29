<?php

namespace HireHq\ConsoleIntegration\Http\Middleware;

use Closure;
use HireHq\ConsoleIntegration\Contracts\EntitlementRepository;
use HireHq\ConsoleIntegration\Contracts\ProductContextResolver;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureProductEntitlement
{
    public function __construct(private ProductContextResolver $contexts, private EntitlementRepository $entitlements, private Repository $config) {}

    public function handle(Request $request, Closure $next): Response
    {
        $context = $this->contexts->resolve($request);
        if ($context !== null && $context->companyId !== '' && $context->environment !== '' && $context->product !== ''
            && $context->environment === $this->config->get('console-integration.environment')
            && $context->product === $this->config->get('console-integration.product')
            && $this->entitlements->find($context)?->allowsAccess(now()->timestamp)) {
            return $next($request);
        }

        // Do not expose a hire company's billing state to its customer-side users.
        $message = __('This product is currently unavailable. Please contact your administrator.');

        return $request->expectsJson()
            ? response()->json(['code' => 'product_unavailable', 'message' => $message], 403)->header('Cache-Control', 'no-store')
            : response()->view('console-integration::unavailable', ['message' => $message], 403)->header('Cache-Control', 'no-store');
    }
}
