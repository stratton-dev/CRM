<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Gus\BirService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class GusController extends Controller
{
    public function byNip(Request $request, BirService $service): JsonResponse
    {
        try {
            $data = $service->lookupByNip((string) $request->input('nip', ''));
            return response()->json($data);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (RuntimeException $exception) {
            $status = $exception->getCode() === 404 ? 404 : 500;
            return response()->json([
                'message' => $exception->getMessage(),
            ], $status);
        }
    }
}
