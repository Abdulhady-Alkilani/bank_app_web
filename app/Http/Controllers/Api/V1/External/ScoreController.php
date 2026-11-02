<?php

namespace App\Http\Controllers\Api\V1\External;

use App\Contracts\ScoringPipeline;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\EventRequest;
use App\Http\Resources\Api\V1\ScoreResource;

class ScoreController extends Controller
{
    /**
     * POST /score — synchronous scoring (target < 300 ms).
     */
    public function store(EventRequest $request, ScoringPipeline $pipeline): ScoreResource
    {
        return new ScoreResource($pipeline->process($request->payload(), true));
    }
}
