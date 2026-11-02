<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AlertStatus;
use App\Enums\AnalystDecision;
use App\Enums\Priority;
use App\Http\Controllers\Controller;
use App\Http\Resources\AlertResource;
use App\Models\Account;
use App\Models\Alert;
use App\Services\Graph\GraphService;
use App\Services\Investigation\AlertDecisionService;
use App\Services\Investigation\AlertQueue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StaffAlertController extends Controller
{
    public function index(Request $request, AlertQueue $queue): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Alert::class);

        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(AlertStatus::class)],
            'priority' => ['nullable', Rule::enum(Priority::class)],
            'min_score' => ['nullable', 'integer', 'between:0,100'],
            'max_score' => ['nullable', 'integer', 'between:0,100'],
            'channel' => ['nullable', 'string', 'max:50'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'min_amount' => ['nullable', 'numeric', 'min:0'],
            'max_amount' => ['nullable', 'numeric', 'min:0'],
            'mine' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $alerts = $queue->query($request->user(), $filters)->paginate((int) ($filters['per_page'] ?? 20))->withQueryString();

        return AlertResource::collection($alerts);
    }

    public function show(Alert $alert): AlertResource
    {
        Gate::authorize('view', $alert);

        return new AlertResource($alert->load(['riskScore.transaction.account', 'riskScore.event.account']));
    }

    public function decide(Request $request, Alert $alert, AlertDecisionService $service): AlertResource
    {
        Gate::authorize('decide', $alert);

        $data = $request->validate([
            'decision' => ['required', Rule::enum(AnalystDecision::class)],
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        $alert = $service->decide($alert, AnalystDecision::from($data['decision']), $data['reason'], $request->user());

        return new AlertResource($alert->load(['riskScore.transaction.account', 'riskScore.event.account']));
    }

    public function network(Request $request, Account $account, GraphService $graph): JsonResponse
    {
        Gate::authorize('viewAny', Alert::class);

        $data = $request->validate(['depth' => ['nullable', 'integer', 'between:1,3']]);

        return response()->json(['data' => $graph->networkForAccount($account, (int) ($data['depth'] ?? 2))]);
    }
}
