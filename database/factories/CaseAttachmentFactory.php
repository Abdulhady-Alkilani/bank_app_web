<?php

namespace Database\Factories;

use App\Models\CaseAttachment;
use App\Models\InvestigationCase;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CaseAttachment>
 */
class CaseAttachmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'case_id' => InvestigationCase::factory(),
            'uploaded_by' => User::factory(),
            'file_name' => 'evidence.pdf',
            'file_path' => 'case-attachments/evidence.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 2048,
        ];
    }
}
