<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\McpAccess;
use App\Models\User;
use App\Services\Investigation\AlertInsights;
use App\Services\Settings\SettingsService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('explain_score')]
#[Title('Explain risk score')]
#[Description('Read-only. Explains in plain Arabic and English why an alert received its risk score: decision band, contributing reasons ranked by impact, and notable features. شرح درجة الخطورة.')]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class ExplainScoreTool extends FraudTool
{
    private const DECISIONS = [
        'allow' => ['ar' => 'سماح', 'en' => 'allow'],
        'challenge' => ['ar' => 'تحقق إضافي (OTP)', 'en' => 'challenge (OTP)'],
        'block' => ['ar' => 'حظر', 'en' => 'block'],
    ];

    public function schema(JsonSchema $schema): array
    {
        return [
            'alert_id' => $schema->integer()->description('Alert id')->min(1)->required(),
        ];
    }

    protected function execute(Request $request, User $user): array
    {
        $request->validate(['alert_id' => 'required|integer|min:1']);

        $alert = McpAccess::alertFor($user, $request->get('alert_id'));
        $score = $alert->riskScore;
        $insights = app(AlertInsights::class);
        $settings = app(SettingsService::class);

        $allowMax = (int) $settings->get('decision.allow_max', 39);
        $challengeMax = (int) $settings->get('decision.challenge_max', 74);

        if (! $score) {
            return [
                'alert_id' => $alert->id,
                'explanation_ar' => 'لا توجد درجة خطورة مرتبطة بهذا التنبيه.',
                'explanation_en' => 'No risk score is attached to this alert.',
            ];
        }

        $reasons = collect($insights->reasons($score))
            ->sortByDesc(fn (array $r) => is_numeric($r['impact']) ? (float) $r['impact'] : 0)
            ->values();

        $decision = $score->decision->value;
        $label = self::DECISIONS[$decision];
        $band = match (true) {
            $score->score <= $allowMax => "0-{$allowMax}",
            $score->score <= $challengeMax => ($allowMax + 1).'-'.$challengeMax,
            default => ($challengeMax + 1).'-100',
        };

        $features = collect($score->features ?? []);
        $notable = $features->filter(fn ($v) => $v === true || (is_numeric($v) && (float) $v > 0 && $v !== 0))->keys()->take(8)->all();

        $reasonsAr = $reasons->isEmpty() ? 'لا توجد أسباب مسجلة.' : $reasons->map(fn (array $r) => '- '.$r['text'].($r['impact'] !== null ? " (+{$r['impact']})" : ''))->implode("\n");
        $reasonsEn = $reasons->isEmpty() ? 'No recorded reasons.' : $reasons->map(fn (array $r) => '- '.$r['text'].($r['impact'] !== null ? " (+{$r['impact']})" : ''))->implode("\n");

        $source = $score->used_fallback
            ? ['ar' => 'اعتمد التقييم على القواعد فقط لأن نموذج الذكاء لم يكن متاحاً.', 'en' => 'Scoring used rules only because the AI model was unavailable.']
            : ['ar' => 'جمع التقييم بين درجة القواعد ('.($score->rule_score ?? '-').') واحتمال النموذج ('.($score->model_probability ?? '-').').', 'en' => 'The score combines the rule score ('.($score->rule_score ?? '-').') and the model probability ('.($score->model_probability ?? '-').').'];

        $run = $score->used_fallback ? null : $score->modelRun;
        $modelLabel = $run ? trim(($run->model?->model_name ?? '').' '.$run->version_number) : null;
        $shadow = $score->shadow_probability !== null ? (float) $score->shadow_probability : null;
        $modelAr = $modelLabel ? "النموذج المستخدم: {$modelLabel}." : 'النموذج المستخدم: القواعد فقط.';
        $modelEn = $modelLabel ? "Model used: {$modelLabel}." : 'Model used: rules only.';
        if ($shadow !== null) {
            $modelAr .= " احتمال النموذج الظلي: {$shadow}.";
            $modelEn .= " Shadow model probability: {$shadow}.";
        }

        return [
            'alert_id' => $alert->id,
            'score' => $score->score,
            'decision' => $decision,
            'band' => $band,
            'thresholds' => ['allow_max' => $allowMax, 'challenge_max' => $challengeMax],
            'used_fallback' => (bool) $score->used_fallback,
            'model' => $run ? ['name' => $run->model?->model_name, 'version' => $run->version_number] : null,
            'model_label' => $modelLabel ?? 'rules only',
            'shadow_probability' => $shadow,
            'reasons' => $reasons->all(),
            'notable_features' => $notable,
            'explanation_ar' => "درجة الخطورة {$score->score} من 100 (النطاق {$band}) والقرار الآلي: {$label['ar']}. {$source['ar']} {$modelAr}\nأهم الأسباب:\n{$reasonsAr}\nهذا تفسير آلي للمساعدة فقط؛ القرار النهائي للمحلل.",
            'explanation_en' => "Risk score {$score->score}/100 (band {$band}); automated decision: {$label['en']}. {$source['en']} {$modelEn}\nTop reasons:\n{$reasonsEn}\nThis is an automated explanation to assist; the final decision belongs to the analyst.",
        ];
    }
}
