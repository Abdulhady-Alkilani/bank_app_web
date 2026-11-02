<?php

namespace Database\Seeders;

use App\Enums\ModelStatus;
use App\Models\MlModel;
use App\Models\ModelRun;
use Illuminate\Database\Seeder;

class MlModelSeeder extends Seeder
{
    public function run(): void
    {
        $model = MlModel::updateOrCreate(
            ['model_name' => 'rules-baseline'],
            ['algorithm' => 'logistic', 'status' => ModelStatus::Champion],
        );

        ModelRun::firstOrCreate(
            ['model_id' => $model->id, 'version_number' => '1.0.0'],
            [
                'metrics' => [
                    'auc' => 0.91, 'precision' => 0.78, 'recall' => 0.72, 'f1' => 0.75, 'false_positive_rate' => 0.04,
                    'train_rows' => 12000, 'positive_rate' => 0.031,
                ],
                'trained_at' => now()->subDays(7),
            ],
        );
    }
}
