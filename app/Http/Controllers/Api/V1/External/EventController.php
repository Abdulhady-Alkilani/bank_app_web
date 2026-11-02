<?php

namespace App\Http\Controllers\Api\V1\External;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\EventRequest;
use App\Jobs\ProcessBankEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class EventController extends Controller
{
    /**
     * POST /events — asynchronous ingestion (202). Retries with the same idempotency key never duplicate.
     */
    public function store(EventRequest $request): JsonResponse
    {
        $payload = $request->payload();
        $payload['idempotency_key'] ??= 'evt_'.Str::uuid();

        ProcessBankEvent::dispatch($payload);

        return response()->json([
            'status' => 'accepted',
            'idempotency_key' => $payload['idempotency_key'],
        ], 202);
    }
}
