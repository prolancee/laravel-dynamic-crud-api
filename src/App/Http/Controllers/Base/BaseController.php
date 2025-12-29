<?php

namespace PROLANCEE\DYNAMIC\CRUD\Api\App\Http\Controllers\Base;

use PROLANCEE\Support\App\Services\BaseService;
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\Storage;
use PROLANCEE\Support\Classes\routes\RouterTracker;
use PROLANCEE\Support\Classes\IO\{Uploader, Fetcher};
use PROLANCEE\Support\Classes\Crypto\{Encrypter, Decrypter};
use PROLANCEE\DYNAMIC\CRUD\Api\Classes\Responsor\{AuthorizerResponder, CatchResponder};
use PROLANCEE\DYNAMIC\CRUD\Api\Exceptions\Handler\ApiException;
use PROLANCEE\DYNAMIC\CRUD\Api\App\Http\Controllers\Base\BaseHelperController;
use RuntimeException;
use Exception;
use Throwable;

abstract class BaseController extends BaseHelperController
{
    protected BaseService $service;

    public function __construct(BaseService $service)
    {
        $this->service = $service;
    }

    // -------------------------------
    // Access Token
    // -------------------------------
    protected function generateAccessTokenControlBase(Request $req, string $operation): JsonResponse
    {
        try {
            $reCycle_key = $req->header('Recycle-Key');
            $client_id   = $req->header('Client-Id');

            if (empty($reCycle_key)) return AuthorizerResponder::missingReCycleKeyHeader($req);
            if (!$req->filled('api_key') || !$req->filled('secret_key')) return AuthorizerResponder::requiredApiCredentials($req);

            $result = $this->generateAccessToken(
                (string) $reCycle_key,
                (string) $req->input('api_key'),
                (string) $req->input('secret_key'),
                (string) $client_id
            );
            return $this->standardizedResponse($result, $operation);

        } catch (RuntimeException $e) {
            return CatchResponder::runtime($e->getMessage());
        } catch (Exception $e) {
            return CatchResponder::generic($e->getMessage());
        } catch (Throwable $e) {
            return CatchResponder::throwable($e->getMessage());
        } finally {
            RouterTracker::trackRoute('api');
        }
    }

    // -------------------------------
    // Auth Control
    // -------------------------------
    protected function authControlBase(Request $req, string $operation, string $table = null): JsonResponse
    {
        try {
            $payload  = $req->input('decryption', []);
            $notifier = $req->header('Notifier');
            $method   = $operation;
            
            $params = [
                'table'     => (string) ($table ?? $payload['table'] ?? ''),
                'column'    => (string) ($payload['column']   ?? ''),
                'unique'    => (string) ($payload['unique']   ?? ''),
                'data'      => (array)  ($payload['data']     ?? []),
                'email'     => (string) ($payload['email']    ?? ''),
                'password'  => (string) ($payload['password'] ?? ''),
                'token'     => (string) ($payload['token']    ?? ''),
                'notifier'  => (string) ($notifier ?? ''),
                'operation' => (string) ($operation ?? ''),
            ];

            $result = $this->service->{$method}($params);
            return $this->queryResponse($result, $operation);

        } catch (RuntimeException $e) {
            return CatchResponder::runtime($e->getMessage());
        } catch (Exception $e) {
            return CatchResponder::generic($e->getMessage());
        } catch (Throwable $e) {
            return CatchResponder::throwable($e->getMessage());
        } finally {
            RouterTracker::trackRoute('api');
        }
    }

    // -------------------------------
    // Single Control
    // -------------------------------
    protected function singleControlBase(Request $req, string $operation, string $table = null): JsonResponse
    {
        try {
            $payload  = $req->input('decryption', []);
            $notifier = $req->header('Notifier');
            $method   = $operation.'Single';
            
            $params = [
                'table'     => (string) ($table ?? $payload['table'] ?? ''),
                'column'    => (string) ($payload['column']  ?? ''),
                'unique'    => (string) ($payload['unique']  ?? ''),
                'data'      => (array)  ($payload['data']    ?? []),
                'notifier'  => (string) ($notifier ?? ''),
                'operation' => (string) ($operation ?? ''),
            ];

            $result = $this->service->{$method}($params);
            return $this->queryResponse($result, $operation);

        } catch (RuntimeException $e) {
            return CatchResponder::runtime($e->getMessage());
        } catch (Exception $e) {
            return CatchResponder::generic($e->getMessage());
        } catch (Throwable $e) {
            return CatchResponder::throwable($e->getMessage());
        } finally {
            RouterTracker::trackRoute('api');
        }
    }

    // -------------------------------
    // Bulk Control
    // -------------------------------
    protected function bulkControlBase(Request $req, string $operation, array $tables = []): JsonResponse
    {
        try {
            $payload  = $req->input('decryption', []);
            $notifier = $req->header('Notifier');
            $method   = $operation . 'Bulk';

            $params = [
                'table'     => (array) ($tables ?? ($payload['table'] ?? [])),
                'column'    => (array) ($payload['column']  ?? []),
                'unique'    => (array) ($payload['unique']  ?? []),
                'data'      => (array) ($payload['data']    ?? []),
                'notifier'  => (string) ($notifier ?? ''),
                'operation' => (string) ($operation ?? ''),
            ];

            $result = $this->service->{$method}($params);
            return $this->queryResponse($result, $operation);

        } catch (RuntimeException $e) {
            return CatchResponder::runtime($e->getMessage());
        } catch (Exception $e) {
            return CatchResponder::generic($e->getMessage());
        } catch (Throwable $e) {
            return CatchResponder::throwable($e->getMessage());
        } finally {
            RouterTracker::trackRoute('api');
        }
    }

    // -------------------------------
    // Fetch Builder Control
    // -------------------------------
    protected function fetchBuilderControlBase(Request $req, string $operation, string $table = null): JsonResponse
    {
        try {
            $payload  = $req->input('decryption', []);
            $notifier = $req->header('Notifier');
            $method   = $operation;
            
            $params = array_merge(['NOTIFIER' => $notifier , 'OPERATION' => $operation],  $payload);

            $result = Fetcher::$method($params);
            return $this->queryResponse($result, $operation);

        } catch (RuntimeException $e) {
            return CatchResponder::runtime($e->getMessage());
        } catch (Exception $e) {
            return CatchResponder::generic($e->getMessage());
        } catch (Throwable $e) {
            return CatchResponder::throwable($e->getMessage());
        } finally {
            RouterTracker::trackRoute('api');
        }
    }

    // -------------------------------
    // Upload Files
    // -------------------------------
    protected function filesUploadControlBase(Request $req, string $operation): JsonResponse
    {
        try {
            $payload = $req->input('decryption', []);

            $files = $payload['files'] ?? (isset($payload['file']) ? [$payload['file']] : []);
            $result = [];

            if ($files) {
                $result = Uploader::handleFiles(
                    $files,
                    (string) ($payload['folder'] ?? 'default'),
                    (array) ($payload['type'] ?? []),
                    (int) ($payload['size'] ?? 0)
                );
            }
            return $this->standardizedResponse($result, $operation);

        } catch (RuntimeException $e) {
            return CatchResponder::runtime($e->getMessage());
        } catch (Exception $e) {
            return CatchResponder::generic($e->getMessage());
        } catch (Throwable $e) {
            return CatchResponder::throwable($e->getMessage());
        } finally {
            RouterTracker::trackRoute('api');
        }
    }

    // -------------------------------
    // Delete Uploaded Files
    // -------------------------------
    protected function filesDeleteControlBase(Request $req, string $operation): JsonResponse
    {
        try {
            $payload = $req->input('decryption', []);
            $folders = $payload['upload']['folder'] ?? [];
            $files   = $payload['upload']['fileName'] ?? [];

            $results = [];

            foreach ($folders as $index => $folder) {
                $fileName = $files[$index] ?? null;
                if (!$fileName) continue;

                $filePath = "upload/{$folder}/{$fileName}";

                if (Storage::disk('public')->exists($filePath)) {
                    $deleted = Storage::disk('public')->delete($filePath);
                    $results[] = [
                        'file'   => $filePath,
                        'status' => $deleted
                    ];
                } else {
                    $results[] = [
                        'file'   => $filePath,
                        'status' => false
                    ];
                }
            }
            return $this->standardizedResponse($results, $operation);

        } catch (RuntimeException $e) {
            return CatchResponder::runtime($e->getMessage());
        } catch (Exception $e) {
            return CatchResponder::generic($e->getMessage());
        } catch (Throwable $e) {
            return CatchResponder::throwable($e->getMessage());
        } finally {
            RouterTracker::trackRoute('api');
        }
    }
}
