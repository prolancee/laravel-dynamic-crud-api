<?php

namespace PROLANCEE\DYNAMIC\CRUD\Api\App\Http\Middleware;

use PROLANCEE\DYNAMIC\CRUD\Api\App\Http\Middleware\Base\Decryptor;
use Illuminate\Http\{Request, JsonResponse};
use PROLANCEE\Support\Classes\Http\DomainChecker;
use PROLANCEE\DYNAMIC\CRUD\Api\Classes\Responsor\{AuthorizerResponder, CatchResponder};
use PROLANCEE\Support\Classes\Routes\RouterTracker;
use Closure;
use RuntimeException;
use Exception;
use Throwable;

class Sanctum extends Decryptor
{
    public function handle(Request $req, Closure $next)
    {
        $fromUri = RouterTracker::getFromUri(); 
        $endpoint = $fromUri['endpoint'] ?? [];

        $auth = [
            'auth/register',
            'auth/login',
            'auth/social',
            'password/reset-token',
            'change/password'
        ];

        if (!in_array($endpoint, $auth)) {
            $tokenCheck = $this->prepareAuthorization($req);
            if ($tokenCheck instanceof JsonResponse) return $tokenCheck;
        }
        
        $paramsCheck = $this->decryptParams($req);
        if ($paramsCheck instanceof JsonResponse) return $paramsCheck;

        return $next($req);
    } 

    /**
     * Check for Bearer token in Authorization header and validate domain.
     *
     * Returns a JsonResponse if the token is missing or an error occurs;
     * otherwise returns null.
     *
     * @param Request $req The HTTP request.
     * @return JsonResponse|null
     */
    private function prepareAuthorization(Request $req): string|JsonResponse
    {
        try {
            DomainChecker::boot();
            DomainChecker::checkDomainBySameSite();

            $bearerToken = $req->header('Authorization');

            if (empty($bearerToken)) {
                return AuthorizerResponder::bearerTokenMissing();
            }

            if (!auth('sanctum')->check()) {
                return AuthorizerResponder::sanctumAuthentication();
            }

           return $bearerToken;
        } catch (RuntimeException $e) {
           return CatchResponder::runtime($req, $e->getMessage());
        } catch (Exception $e) {
            return CatchResponder::generic($e->getMessage());
        } catch (Throwable $e) {
            return CatchResponder::throwable($req, $e->getMessage());
        }
    } 
} 