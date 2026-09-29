<?php

namespace HireHq\ConsoleIntegration\Support;

final class MessageSignature
{
    public function sign(string $key, string $keyId, int $timestamp, string $method, string $path, string $body): string
    {
        return hash_hmac('sha256', implode("\n", ['v1', $keyId, (string) $timestamp, strtoupper($method), $path, $body]), $key);
    }
}
