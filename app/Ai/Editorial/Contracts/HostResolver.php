<?php

namespace App\Ai\Editorial\Contracts;

interface HostResolver
{
    /** @return list<string> */
    public function addresses(string $host): array;
}
