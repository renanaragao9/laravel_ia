<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMcpToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('bicho.mcp.token');

        abort_if($expected === '', 403, 'MCP_BICHO_TOKEN não configurado.');
        abort_unless(hash_equals($expected, (string) $request->bearerToken()), 401, 'Token MCP inválido.');

        return $next($request);
    }
}
