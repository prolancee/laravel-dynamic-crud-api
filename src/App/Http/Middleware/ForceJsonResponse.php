<?php

namespace PROLANCEE\DYNAMIC\CRUD\Api\App\Http\Middleware;

use Closure;

class ForceJsonResponse
{
    public function handle($req, Closure $next)
    {
        $req->headers->set('Accept', 'application/json');
        return $next($req);
    }
}
