<?php

namespace HireHq\ConsoleIntegration\Http\Middleware;

use Closure;
use HireHq\ConsoleIntegration\Support\MessageSignature;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class VerifyConsoleSignature
{
    public function __construct(private Repository $config, private MessageSignature $signatures) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (strlen($request->getContent()) > 16384) {
            return response()->json(['message' => 'Payload too large.'], 413);
        }

        $keyId = $request->header('X-Console-Key-Id', '');
        $keys = $this->config->get('console-integration.signing_keys', []);
        $key = $keys[$keyId] ?? null;
        $timestamp = $request->header('X-Console-Timestamp', '');
        $signature = $request->header('X-Console-Signature', '');
        $tolerance = max(0, min(300, (int) $this->config->get('console-integration.signature_tolerance_seconds', 300)));

        if (! is_string($key) || strlen($key) < 32 || ! preg_match('/^[0-9]{1,12}$/D', $timestamp)
            || abs(now()->timestamp - (int) $timestamp) > $tolerance
            || ! preg_match('/^[a-f0-9]{64}$/D', $signature)
            || ! hash_equals($this->signatures->sign($key, $keyId, (int) $timestamp, $request->method(), $request->getPathInfo(), $request->getContent()), $signature)) {
            return response()->json(['message' => 'Invalid Console signature.'], 401);
        }

        return $next($request);
    }
}
