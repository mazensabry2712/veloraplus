<?php

namespace App\Infrastructure\Tenancy;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class TenantDatabaseManager
{
    public const CONNECTION = 'tenant';

    private ?string $previousDefaultConnection = null;

    public function configure(Tenant $tenant): void
    {
        $template = config('database.connections.tenant_template');

        if ($template === null) {
            throw new RuntimeException('Tenant database template connection is not configured.');
        }

        $template['database'] = $tenant->database_name;
        $template['host'] = $tenant->database_host ?: $template['host'];
        $template['port'] = $tenant->database_port ?: $template['port'];

        config(['database.connections.'.self::CONNECTION => $template]);
        DB::purge(self::CONNECTION);
    }

    public function connect(Tenant $tenant): void
    {
        $this->configure($tenant);
        $this->previousDefaultConnection ??= config('database.default');
        DB::setDefaultConnection(self::CONNECTION);
        DB::connection(self::CONNECTION)->getPdo();
    }

    public function disconnect(): void
    {
        DB::purge(self::CONNECTION);

        if ($this->previousDefaultConnection !== null) {
            DB::setDefaultConnection($this->previousDefaultConnection);
            $this->previousDefaultConnection = null;
        }
    }
}