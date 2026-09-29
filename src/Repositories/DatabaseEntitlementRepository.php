<?php

namespace HireHq\ConsoleIntegration\Repositories;

use HireHq\ConsoleIntegration\Contracts\EntitlementRepository;
use HireHq\ConsoleIntegration\Data\EntitlementSnapshot;
use HireHq\ConsoleIntegration\Data\ProductContext;
use HireHq\ConsoleIntegration\Events\EntitlementUpdated;
use HireHq\ConsoleIntegration\Exceptions\RevisionConflict;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\DatabaseManager;

final class DatabaseEntitlementRepository implements EntitlementRepository
{
    public function __construct(private DatabaseManager $database, private Repository $config, private Dispatcher $events) {}

    public function find(ProductContext $context): ?EntitlementSnapshot
    {
        $row = $this->database->connection($this->config->get('console-integration.connection'))
            ->table('console_entitlements')->where($context->key())->first();

        return $row ? new EntitlementSnapshot($context, (int) $row->revision, $row->status,
            (int) $row->valid_until, $row->trial_ends_at === null ? null : (int) $row->trial_ends_at,
            $row->access_ends_at === null ? null : (int) $row->access_ends_at) : null;
    }

    public function store(EntitlementSnapshot $snapshot): bool
    {
        $connection = $this->database->connection($this->config->get('console-integration.connection'));
        $hash = hash('sha256', json_encode($snapshot->toArray(), JSON_THROW_ON_ERROR));

        return $connection->transaction(function () use ($connection, $snapshot, $hash): bool {
            $query = $connection->table('console_entitlements')->where($snapshot->context->key());
            $inserted = $connection->table('console_entitlements')->insertOrIgnore([
                ...$snapshot->toArray(), 'payload_hash' => $hash,
            ]);
            $row = $query->lockForUpdate()->first();

            if (! $inserted) {
                if ((int) $row->revision > $snapshot->revision || ((int) $row->revision === $snapshot->revision && ! hash_equals($row->payload_hash, $hash))) {
                    throw new RevisionConflict('An equal or newer entitlement revision is already stored.');
                }
                if ((int) $row->revision === $snapshot->revision) {
                    return false;
                }
                $query->update([...$snapshot->toArray(), 'payload_hash' => $hash]);
            }

            $this->events->dispatch(new EntitlementUpdated($snapshot));

            return true;
        }, 3);
    }
}
