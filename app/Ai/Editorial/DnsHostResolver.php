<?php

namespace App\Ai\Editorial;

use App\Ai\Editorial\Contracts\HostResolver;

class DnsHostResolver implements HostResolver
{
    public function addresses(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $records = dns_get_record($host, DNS_A | DNS_AAAA);

        return collect($records ?: [])->flatMap(fn (array $record): array => array_filter([
            $record['ip'] ?? null,
            $record['ipv6'] ?? null,
        ]))->unique()->values()->all();
    }
}
