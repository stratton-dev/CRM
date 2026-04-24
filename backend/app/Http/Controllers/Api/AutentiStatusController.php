<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Autenti\AutentiClientFactory;
use Autenti\Exceptions\HttpException;
use Illuminate\Http\JsonResponse;

class AutentiStatusController extends Controller
{
    public function __invoke(AutentiClientFactory $factory): JsonResponse
    {
        if (!config('autenti.enabled')) {
            return response()->json([
                'ok' => false,
                'enabled' => false,
                'message' => 'Autenti disabled',
            ]);
        }

        try {
            $client = $factory->make();
            $response = $client->me()->get();
            $data = $response->json();

            return response()->json([
                'ok' => true,
                'enabled' => true,
                'status_code' => $response->getStatusCode(),
                'data' => $data,
            ]);
        } catch (HttpException $e) {
            $resp = $e->getResponse();

            return response()->json([
                'ok' => false,
                'enabled' => true,
                'status_code' => $resp->getStatusCode(),
                'error' => $resp->json() ?: $resp->getBody(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'enabled' => true,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
