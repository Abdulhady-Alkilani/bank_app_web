<?php

namespace App\Livewire\Supervisor;

use App\Livewire\Concerns\AuthorizesPermission;
use App\Services\Reports\SupervisorStats;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Component;

class Dashboard extends Component
{
    use AuthorizesPermission;

    public int|string $days = 14;

    public function mount(): void
    {
        $this->authorizePermission('reports.view');
    }

    public function render(SupervisorStats $stats): View
    {
        $days = in_array((int) $this->days, [7, 14, 30, 90], true) ? (int) $this->days : 14;
        $summary = $stats->summary(Carbon::today()->subDays($days - 1), Carbon::today());

        $decisionLabels = collect($summary['decisions'])->mapWithKeys(fn ($count, $key) => [
            $key === 'none' ? __('supervisor.dashboard.undecided') : __('enums.AnalystDecision.'.$key) => $count,
        ]);

        $charts = [
            'perDay' => [
                'chart' => ['type' => 'area', 'height' => 280, 'toolbar' => ['show' => false], 'fontFamily' => 'inherit'],
                'series' => [['name' => __('supervisor.dashboard.alerts'), 'data' => array_values($summary['alerts_per_day'])]],
                'xaxis' => ['categories' => array_keys($summary['alerts_per_day'])],
                'stroke' => ['curve' => 'smooth', 'width' => 2],
                'dataLabels' => ['enabled' => false],
            ],
            'workload' => [
                'chart' => ['type' => 'bar', 'height' => 280, 'toolbar' => ['show' => false], 'fontFamily' => 'inherit'],
                'series' => [['name' => __('supervisor.dashboard.open_alerts'), 'data' => array_values($summary['workload'])]],
                'xaxis' => ['categories' => array_keys($summary['workload'])],
                'plotOptions' => ['bar' => ['horizontal' => true]],
                'dataLabels' => ['enabled' => true],
            ],
            'decisions' => [
                'chart' => ['type' => 'donut', 'height' => 280, 'fontFamily' => 'inherit'],
                'series' => $decisionLabels->values()->all(),
                'labels' => $decisionLabels->keys()->all(),
                'legend' => ['position' => 'bottom'],
            ],
        ];

        return view('livewire.supervisor.dashboard', [
            's' => $summary,
            'charts' => $charts,
            'periods' => [7, 14, 30, 90],
        ])->title(__('supervisor.dashboard.title'));
    }
}
