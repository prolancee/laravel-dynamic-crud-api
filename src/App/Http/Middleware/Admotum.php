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

class Admotum extends Decryptor
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
            $tokenCheck = $this->prepareAccessToken($req);
            if ($tokenCheck instanceof JsonResponse) return $tokenCheck;
        }
        
        $paramsCheck = $this->decryptParams($req);
        if ($paramsCheck instanceof JsonResponse) return $paramsCheck;

        return $next($req);
    }

    /**
     * Validates headers and token; returns JsonResponse on failure.
     *
     * @param Request $req
     * @return JsonResponse|null
     */
    private function prepareAccessToken(Request $req): string|JsonResponse
    {
        try {
            DomainChecker::boot();
            DomainChecker::checkDomainBySameSite();

            $reCycleKey  = $req->header('Recycle-Key');
            $accessToken = $req->header('Access-Token');
            $clientId    = $req->header('Client-Id');

            if (empty($reCycleKey)) return AuthorizerResponder::missingReCycleKeyHeader();
            if (empty($accessToken)) return AuthorizerResponder::missingAccessTokenHeader();

            $verified = AuthorizerResponder::verifyAccessTokenByAdmotum((string) $reCycleKey, (string) $accessToken, (string) $clientId);
            if ($verified instanceof JsonResponse) return $verified;

            return $verified;
        } catch (RuntimeException $e) {
            return CatchResponder::runtime($e->getMessage());
        } catch (Exception $e) {
            return CatchResponder::generic($e->getMessage());
        } catch (Throwable $e) {
            return CatchResponder::throwable($e->getMessage());
        }
    }
}
