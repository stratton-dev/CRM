<?php

namespace App\Http\Controllers\Api;

use App\Actions\Offer\OpenOfferAction;
use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Http\JsonResponse;

class PublicOffersController extends Controller
{
    public function __invoke(string $token, OpenOfferAction $action): JsonResponse
    {
        try {
            $offer = $action->execute($token);
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 403);
        }

        return response()->json($offer);
    }
}
