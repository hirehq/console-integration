<?php

namespace HireHq\ConsoleIntegration\Http\Controllers;

use HireHq\ConsoleIntegration\Contracts\EntitlementRepository;
use HireHq\ConsoleIntegration\Data\EntitlementSnapshot;
use HireHq\ConsoleIntegration\Data\ProductContext;
use HireHq\ConsoleIntegration\Exceptions\RevisionConflict;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ReceiveEntitlement
{
    public function __construct(private EntitlementRepository $entitlements, private Repository $config, private Factory $validator) {}

    public function __invoke(Request $request): JsonResponse
    {
        if (! $request->isJson()) {
            return response()->json(['message' => 'A JSON body is required.'], 415);
        }
        $data = $request->json()->all();
        $rules = [
            'schema_version' => ['required', 'integer', 'in:1'],
            'environment' => ['required', 'string', 'max:32', 'regex:/^[a-z0-9_-]+$/D'],
            'product' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_-]+$/D'],
            'company_id' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/D'],
            'revision' => ['required', 'integer', 'min:1', 'max:9007199254740991'],
            'status' => ['required', 'string', 'in:active,trialing,canceled,suspended,expired,past_due,incomplete'],
            'valid_until' => ['required', 'integer', 'min:1'],
            'trial_ends_at' => ['nullable', 'required_if:status,trialing', 'integer', 'min:1'],
            'access_ends_at' => ['nullable', 'integer', 'min:1'],
        ];
        $validation = $this->validator->make($data, $rules);
        if ($validation->fails() || array_diff(array_keys($data), array_keys($rules))) {
            return response()->json(['message' => 'Invalid entitlement payload.', 'errors' => $validation->errors()], 422);
        }
        if (! $this->config->get('console-integration.environment') || ! $this->config->get('console-integration.product')
            || $data['environment'] !== $this->config->get('console-integration.environment')
            || $data['product'] !== $this->config->get('console-integration.product')) {
            return response()->json(['message' => 'Integration scope does not match.'], 403);
        }
        $maximumLease = max(1, min(3600, (int) $this->config->get('console-integration.maximum_lease_seconds', 300)));
        if ((int) $data['valid_until'] > now()->timestamp + $maximumLease) {
            return response()->json(['message' => 'Entitlement lease exceeds the configured limit.'], 422);
        }
        $snapshot = new EntitlementSnapshot(
            new ProductContext($data['environment'], $data['product'], $data['company_id']),
            (int) $data['revision'], $data['status'], (int) $data['valid_until'],
            isset($data['trial_ends_at']) ? (int) $data['trial_ends_at'] : null,
            isset($data['access_ends_at']) ? (int) $data['access_ends_at'] : null,
        );
        try {
            $changed = $this->entitlements->store($snapshot);
        } catch (RevisionConflict) {
            return response()->json(['message' => 'Entitlement revision conflict.'], 409);
        }

        return response()->json(['changed' => $changed, 'revision' => $snapshot->revision]);
    }
}
