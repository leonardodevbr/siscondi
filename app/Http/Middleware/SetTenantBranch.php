<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Branch;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetTenantBranch
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return $next($request);
        }

        $user = $request->user();

        $branchId = null;

        $isSuperAdmin = method_exists($user, 'hasRole') && $user->hasRole('super-admin');

        if ($isSuperAdmin) {
            $headerBranchId = $request->header('X-Branch-ID');

            if ($headerBranchId !== null && $headerBranchId !== '') {
                $candidateId = (int) $headerBranchId;

                if ($candidateId > 0 && Branch::whereKey($candidateId)->exists()) {
                    $branchId = $candidateId;
                }
            }
        }

        if ($branchId === null) {
            $branchId = $user->branch_id ? (int) $user->branch_id : null;

            if (! $branchId) {
                $branchId = (int) (Branch::where('is_main', true)->value('id') ?? 0);
            }
        }

        if ($branchId > 0) {
            app()->instance('current_branch_id', $branchId);
        }

        return $next($request);
    }
}

