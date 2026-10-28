<?php

namespace App\Http\Controllers\Webhooks;

use App\Application\Payments\KashierTenantWebhookHandler;
use App\Infrastructure\Payments\Kashier\Exceptions\InvalidWebhookSignature;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class KashierTenantWebhookController
{
    public function __invoke(Request $request, KashierTenantWebhookHandler $handler): JsonResponse
    {
        try {
            $result = $handler->handle(
                $request->json()->all(),
                $request->headers->all(),
                $request->getContent(),
            );
        } catch (InvalidWebhookSignature $exception) {
            return response()->json(['message' => $exception->getMessage()], 401);
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        // A verified duplicate has already been applied. A 2xx response is
        // required to acknowledge delivery and stop provider retry loops.
        return response()->json([], 200);
    }
}
