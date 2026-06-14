<?php

namespace Shoptimised\AiVisibility\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Shoptimised\AiVisibility\Support\TenantContext;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the active retailer for the request.
 *
 * - Retailer users: tenant is their own retailer_id (hard isolation).
 * - Shoptimised staff: no tenant set, so queries are unscoped; the policies
 *   gate which retailers they may actually touch. Staff may scope themselves to
 *   one retailer via ?retailer={id} (or a session selection) — guarded by
 *   canAccessRetailer() so analysts cannot scope to unassigned retailers.
 */
class SetTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            if ($user->retailer_id) {
                app(TenantContext::class)->set($user->retailer_id);
            } elseif ($request->filled('retailer')) {
                $retailerId = (int) $request->integer('retailer');
                if ($user->canAccessRetailer($retailerId)) {
                    app(TenantContext::class)->set($retailerId);
                }
            }
        }

        return $next($request);
    }
}
