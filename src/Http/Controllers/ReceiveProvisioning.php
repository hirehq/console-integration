<?php

namespace HireHq\ConsoleIntegration\Http\Controllers;

use HireHq\ConsoleIntegration\Contracts\CompanyProvisioner;
use HireHq\ConsoleIntegration\Contracts\EntitlementRepository;
use HireHq\ConsoleIntegration\Data\CompanyProvisioningRequest;
use HireHq\ConsoleIntegration\Data\ProductContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class ReceiveProvisioning
{
    public function __invoke(Request $request, EntitlementRepository $entitlements): JsonResponse
    {
        abort_unless($request->isJson(), 415);
        $data = Validator::make(['payload' => $request->json()->all()], [
            'payload' => ['required', 'array:schema_version,environment,product,company_id,revision,operation_id,name,workos_organisation_id,existing_tenant_id,connection,users'],
            'payload.schema_version' => ['required', 'integer', 'in:1'],
            'payload.environment' => ['required', 'string', 'max:32'],
            'payload.product' => ['required', 'string', 'max:64'],
            'payload.company_id' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/D'],
            'payload.revision' => ['required', 'integer', 'min:1', 'max:9007199254740991'],
            'payload.operation_id' => ['required', 'uuid'],
            'payload.name' => ['required', 'string', 'max:255'],
            'payload.workos_organisation_id' => ['required', 'string', 'regex:/^org_[A-Za-z0-9]+$/D'],
            'payload.existing_tenant_id' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/D'],
            'payload.connection' => ['required', 'array:id,version,host,port,database,secret_reference'],
            'payload.connection.id' => ['required', 'ulid'],
            'payload.connection.version' => ['required', 'ulid'],
            'payload.connection.host' => ['required', 'string', 'max:253'],
            'payload.connection.port' => ['required', 'integer', 'between:1,65535'],
            'payload.connection.database' => ['required', 'string', 'max:128'],
            'payload.connection.secret_reference' => ['required', 'string', 'max:512'],
            'payload.users' => ['present', 'array', 'max:100'],
            'payload.users.*' => ['array:membership_id,workos_user_id,email,role'],
            'payload.users.*.membership_id' => ['required', 'ulid', 'distinct'],
            'payload.users.*.workos_user_id' => ['required', 'string', 'max:64', 'distinct', 'regex:/^user_[A-Za-z0-9]+$/D'],
            'payload.users.*.email' => ['required', 'email', 'max:255'],
            'payload.users.*.role' => ['required', 'string', 'max:32'],
        ])->validate()['payload'];
        abort_unless(config('console-integration.product') && config('console-integration.environment')
            && $data['product'] === config('console-integration.product') && $data['environment'] === config('console-integration.environment'), 403);
        $context = new ProductContext($data['environment'], $data['product'], $data['company_id']);
        $snapshot = $entitlements->find($context);
        abort_unless($snapshot && $snapshot->revision === (int) $data['revision'] && $snapshot->allowsAccess(now()->timestamp), 409);
        abort_unless(app()->bound(CompanyProvisioner::class), 503);
        $provisioner = app(CompanyProvisioner::class);
        $dto = new CompanyProvisioningRequest($context, $data['operation_id'], $data['name'], $data['workos_organisation_id'], $data['connection'], $data['users'], (int) $data['revision']);
        $tenantId = isset($data['existing_tenant_id']) ? $provisioner->linkExisting($dto, $data['existing_tenant_id']) : $provisioner->provision($dto);

        return response()->json(['tenant_id' => $tenantId, 'company_id' => $context->companyId, 'revision' => $dto->revision]);
    }
}
