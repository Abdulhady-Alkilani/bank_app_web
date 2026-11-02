<?php

namespace Database\Seeders;

use App\Models\Rule;
use Illuminate\Database\Seeder;

class RuleSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->rules() as $priority => $rule) {
            Rule::updateOrCreate(
                ['rule_name' => $rule['rule_name']],
                $rule + ['priority' => ($priority + 1) * 10, 'is_active' => true],
            );
        }
    }

    /**
     * Boolean features are expressed as 1 / 0.
     *
     * @return array<int, array<string, mixed>>
     */
    private function rules(): array
    {
        return [
            [
                'rule_name' => 'تحويل كبير بعد تغيير الهاتف',
                'description' => 'تحويل يتجاوز 10 أضعاف متوسط الرصيد خلال 60 دقيقة من تغيير رقم الهاتف/الشريحة',
                'weight' => 45,
                'action' => 'challenge',
                'conditions' => ['event_types' => ['transfer'], 'all' => [
                    ['feature' => 'amount_to_avg_ratio', 'op' => '>=', 'value' => 10],
                    ['feature' => 'minutes_since_phone_change', 'op' => '<=', 'value' => 60],
                ]],
            ],
            [
                'rule_name' => 'جهاز جديد وموقع مختلف',
                'description' => 'عملية من جهاز غير معروف وموقع جغرافي مختلف عن المعتاد',
                'weight' => 30,
                'action' => 'challenge',
                'conditions' => ['event_types' => ['login', 'transfer'], 'all' => [
                    ['feature' => 'is_new_device', 'op' => '==', 'value' => 1],
                    ['feature' => 'location_changed', 'op' => '==', 'value' => 1],
                ]],
            ],
            [
                'rule_name' => 'مستفيد جديد (عمر الحساب أقل من 7 أيام)',
                'description' => 'تحويل إلى حساب مستفيد عمره أقل من 7 أيام بمبلغ أعلى من المعتاد',
                'weight' => 25,
                'action' => 'flag',
                'conditions' => ['event_types' => ['transfer'], 'all' => [
                    ['feature' => 'beneficiary_account_age_days', 'op' => '<', 'value' => 7],
                    ['feature' => 'amount_to_avg_ratio', 'op' => '>=', 'value' => 3],
                ]],
            ],
            [
                'rule_name' => 'تشتت التحويلات (Fan-out)',
                'description' => 'عدد كبير من الحسابات تحوّل إلى نفس المستفيد خلال ساعة',
                'weight' => 30,
                'action' => 'flag',
                'conditions' => ['event_types' => ['transfer'], 'all' => [
                    ['feature' => 'beneficiary_fan_out_1h', 'op' => '>=', 'value' => 3],
                ]],
            ],
            [
                'rule_name' => 'جهاز مشترك بين أكثر من 3 حسابات',
                'description' => 'نفس الجهاز مستخدم من أكثر من ثلاثة حسابات (مؤشر استيلاء/حلقة احتيال)',
                'weight' => 35,
                'action' => 'challenge',
                'conditions' => ['all' => [
                    ['feature' => 'device_account_count', 'op' => '>', 'value' => 3],
                ]],
            ],
            [
                'rule_name' => 'تطابق القائمة السوداء',
                'description' => 'الجهاز أو الحساب أو عنوان IP أو المستفيد موجود في القائمة السوداء',
                'weight' => 100,
                'action' => 'block',
                'conditions' => ['all' => [
                    ['feature' => 'blacklist_hit', 'op' => '==', 'value' => 1],
                ]],
            ],
            [
                'rule_name' => 'تحويل فوري بعد إضافة مستفيد',
                'description' => 'تحويل كبير خلال 30 دقيقة من إضافة المستفيد',
                'weight' => 30,
                'action' => 'challenge',
                'conditions' => ['event_types' => ['transfer'], 'all' => [
                    ['feature' => 'minutes_since_beneficiary_added', 'op' => '<=', 'value' => 30],
                    ['feature' => 'amount_to_avg_ratio', 'op' => '>=', 'value' => 5],
                ]],
            ],
            [
                'rule_name' => 'محاولات دخول متعددة من أجهزة جديدة',
                'description' => 'ثلاث عمليات دخول أو أكثر من أجهزة جديدة خلال ساعة (سيناريو الاستيلاء)',
                'weight' => 30,
                'action' => 'challenge',
                'conditions' => ['event_types' => ['login'], 'all' => [
                    ['feature' => 'logins_new_device_1h', 'op' => '>=', 'value' => 3],
                ]],
            ],
            [
                'rule_name' => 'كثافة عمليات خلال ساعة',
                'description' => 'ست عمليات أو أكثر خلال ساعة واحدة',
                'weight' => 20,
                'action' => 'flag',
                'conditions' => ['event_types' => ['transfer'], 'all' => [
                    ['feature' => 'txn_count_1h', 'op' => '>=', 'value' => 6],
                ]],
            ],
            [
                'rule_name' => 'تحويل كبير في ساعات الليل',
                'description' => 'تحويل أعلى من 4 أضعاف المتوسط بين منتصف الليل والخامسة فجراً',
                'weight' => 15,
                'action' => 'flag',
                'conditions' => ['event_types' => ['transfer'], 'all' => [
                    ['feature' => 'hour_of_day', 'op' => 'in', 'value' => [0, 1, 2, 3, 4, 5]],
                    ['feature' => 'amount_to_avg_ratio', 'op' => '>=', 'value' => 4],
                ]],
            ],
            [
                'rule_name' => 'تحويل كبير بعد رفع الحد',
                'description' => 'رفع حد التحويل مؤخراً متبوعاً بتحويل أعلى من 5 أضعاف المتوسط',
                'weight' => 35,
                'action' => 'challenge',
                'conditions' => ['event_types' => ['transfer'], 'all' => [
                    ['feature' => 'limit_raised_recently', 'op' => '==', 'value' => 1],
                    ['feature' => 'amount_to_avg_ratio', 'op' => '>=', 'value' => 5],
                ]],
            ],
            [
                'rule_name' => 'مبلغ مرتفع جداً',
                'description' => 'أي تحويل بمبلغ يعادل أو يتجاوز 50,000',
                'weight' => 20,
                'action' => 'flag',
                'conditions' => ['event_types' => ['transfer'], 'any' => [
                    ['feature' => 'amount', 'op' => '>=', 'value' => 50000],
                ]],
            ],
        ];
    }
}
