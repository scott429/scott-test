<?php

namespace Shoptimised\AiVisibility\Support;

/**
 * Holds the active retailer (tenant) for the current request/job.
 *
 * Registered as a singleton in the container. When a retailer id is set,
 * the TenantScope constrains every tenant-owned query to that retailer and
 * BelongsToTenant auto-fills retailer_id on create. When null (e.g. Shoptimised
 * staff, console, or queue bootstrap) queries are unscoped and policies enforce
 * per-retailer access instead.
 */
class TenantContext
{
    protected ?int $retailerId = null;

    public function set(int $retailerId): static
    {
        $this->retailerId = $retailerId;

        return $this;
    }

    public function retailerId(): ?int
    {
        return $this->retailerId;
    }

    public function hasTenant(): bool
    {
        return $this->retailerId !== null;
    }

    public function forget(): static
    {
        $this->retailerId = null;

        return $this;
    }

    /**
     * Run a callback with a specific tenant active, restoring the previous
     * tenant afterwards. Useful inside queued jobs that process one retailer.
     */
    public function runAs(int $retailerId, callable $callback): mixed
    {
        $previous = $this->retailerId;
        $this->retailerId = $retailerId;

        try {
            return $callback();
        } finally {
            $this->retailerId = $previous;
        }
    }
}
