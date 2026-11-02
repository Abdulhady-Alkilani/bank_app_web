<?php

namespace App\Http\Controllers\Api\V1\External;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SyncRequest;
use App\Models\Account;
use App\Models\Customer;
use App\Models\CustomerDevice;
use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SyncController extends Controller
{
    /**
     * POST /sync (EXT-05) — idempotent batch upsert of customers, accounts and devices.
     * Customers are matched by email (else phone); accounts by account_number; devices by fingerprint.
     */
    public function store(SyncRequest $request): JsonResponse
    {
        $counts = ['customers' => 0, 'accounts' => 0, 'devices' => 0];

        DB::transaction(function () use ($request, &$counts) {
            foreach ($request->validated('customers') as $row) {
                $customer = filled($row['email'] ?? null)
                    ? Customer::firstOrNew(['email' => $row['email']])
                    : Customer::firstOrNew(['phone' => $row['phone']]);

                $customer->fill(array_filter([
                    'name' => $row['name'],
                    'phone' => $row['phone'] ?? null,
                    'avg_balance' => $row['avg_balance'] ?? null,
                    'usage_pattern' => $row['usage_pattern'] ?? null,
                ], fn ($v) => $v !== null))->save();
                $counts['customers']++;

                foreach ($row['accounts'] ?? [] as $a) {
                    $account = Account::firstOrNew(['account_number' => $a['account_number']]);
                    $account->fill(array_filter([
                        'customer_id' => $customer->id,
                        'type' => $a['type'] ?? null,
                        'status' => $a['status'] ?? null,
                        'balance' => $a['balance'] ?? null,
                        'transfer_limit' => $a['transfer_limit'] ?? null,
                    ], fn ($v) => $v !== null))->save();
                    $counts['accounts']++;
                }

                foreach ($row['devices'] ?? [] as $d) {
                    $device = Device::firstOrNew(['device_fingerprint' => $d['device_fingerprint']]);
                    if (! empty($d['device_type'])) {
                        $device->device_type = $d['device_type'];
                    }
                    $device->save();

                    $link = CustomerDevice::firstOrNew(['customer_id' => $customer->id, 'device_id' => $device->id]);
                    $link->is_trusted = (bool) ($d['is_trusted'] ?? $link->is_trusted ?? true);
                    $link->save();
                    $counts['devices']++;
                }
            }
        });

        return response()->json(['status' => 'synced'] + $counts);
    }
}
