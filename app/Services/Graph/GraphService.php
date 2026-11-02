<?php

namespace App\Services\Graph;

use App\Models\Account;
use App\Models\AccountEvent;
use App\Models\Beneficiary;
use App\Models\CustomerDevice;
use App\Models\Device;
use App\Models\RiskScore;
use App\Models\Transaction;
use Illuminate\Support\Collection;

/**
 * Builds the Cytoscape-ready link-analysis network around an account.
 * Nodes: accounts, devices, IPs and external beneficiaries; depth-limited BFS (hops from the root account).
 */
class GraphService
{
    private const MAX_NODES = 200;

    private const MAX_FANOUT = 25;

    /**
     * @return array{nodes: array<int, array{data: array<string, mixed>}>, edges: array<int, array{data: array<string, mixed>}>}
     */
    public function networkForAccount(Account $account, int $depth = 3): array
    {
        $result = $this->explore($account, $depth);

        return ['nodes' => array_values($result['nodes']), 'edges' => array_values($result['edges'])];
    }

    /**
     * Devices used by this account that are also used by at least one other account.
     *
     * @return Collection<int, array{device: Device, accounts: Collection<int, Account>}>
     */
    public function sharedDevices(Account $account): Collection
    {
        return Device::query()
            ->whereIn('id', $this->deviceIdsForAccount($account))
            ->get()
            ->map(function (Device $device) use ($account) {
                $others = Account::query()
                    ->whereIn('id', $this->accountIdsForDevice($device->id))
                    ->where('id', '!=', $account->id)
                    ->get();

                return ['device' => $device, 'accounts' => $others];
            })
            ->filter(fn (array $row) => $row['accounts']->isNotEmpty())
            ->values();
    }

    /**
     * Other accounts reachable from this account within $depth hops.
     *
     * @return Collection<int, Account>
     */
    public function relatedAccounts(Account $account, int $depth = 2): Collection
    {
        $ids = $this->explore($account, $depth)['account_ids'];

        return Account::query()->whereIn('id', array_diff($ids, [$account->id]))->get();
    }

    /**
     * @return array{nodes: array<string, array>, edges: array<string, array>, account_ids: array<int, int>}
     */
    private function explore(Account $root, int $maxDepth): array
    {
        $maxDepth = max(0, min($maxDepth, 5));
        $nodes = [];
        $edges = [];
        $accountIds = [$root->id];

        $addNode = function (string $id, string $label, string $type, array $extra = []) use (&$nodes): bool {
            if (isset($nodes[$id])) {
                return false;
            }
            $nodes[$id] = ['data' => ['id' => $id, 'label' => $label, 'type' => $type] + $extra];

            return true;
        };
        $addEdge = function (string $source, string $target, string $label) use (&$edges): void {
            $key = $source.'>'.$target.'#'.$label;
            $edges[$key] ??= ['data' => ['id' => 'e'.count($edges).':'.md5($key), 'source' => $source, 'target' => $target, 'label' => $label]];
        };

        $addNode($this->accountKey($root), $root->account_number, 'account', ['status' => $root->status?->value, 'root' => true]);
        $queue = [[$this->accountKey($root), 'account', $root->id, 0]];

        while ($queue !== [] && count($nodes) < self::MAX_NODES) {
            [$key, $type, $ref, $level] = array_shift($queue);

            if ($level >= $maxDepth) {
                continue;
            }

            foreach ($this->neighbours($type, $ref) as [$nType, $nKey, $nLabel, $nRef, $edgeLabel, $extra]) {
                if (count($nodes) >= self::MAX_NODES) {
                    break;
                }

                $isNew = $addNode($nKey, $nLabel, $nType, $extra);
                $addEdge($key, $nKey, $edgeLabel);

                if ($isNew) {
                    if ($nType === 'account') {
                        $accountIds[] = $nRef;
                    }
                    $queue[] = [$nKey, $nType, $nRef, $level + 1];
                }
            }
        }

        $this->annotateRisk($nodes, array_values(array_unique($accountIds)));

        return ['nodes' => $nodes, 'edges' => $edges, 'account_ids' => array_values(array_unique($accountIds))];
    }

    /**
     * @return array<int, array{0: string, 1: string, 2: string, 3: mixed, 4: string, 5: array}>
     */
    private function neighbours(string $type, mixed $ref): array
    {
        return match ($type) {
            'account' => $this->accountNeighbours((int) $ref),
            'device' => $this->deviceNeighbours((int) $ref),
            'ip' => $this->ipNeighbours((string) $ref),
            'beneficiary' => $this->beneficiaryNeighbours((string) $ref),
            default => [],
        };
    }

    private function accountNeighbours(int $accountId): array
    {
        $account = Account::query()->find($accountId);
        if (! $account) {
            return [];
        }

        $out = [];

        foreach (Device::query()->whereIn('id', $this->deviceIdsForAccount($account))->get() as $device) {
            $out[] = ['device', 'device:'.$device->id, $device->device_type.' '.substr($device->device_fingerprint, 0, 8), $device->id, 'uses', []];
        }

        foreach ($this->ipsForAccount($accountId) as $ip) {
            $out[] = ['ip', 'ip:'.$ip, $ip, $ip, 'ip', []];
        }

        $targets = Beneficiary::query()->where('account_id', $accountId)->pluck('beneficiary_account')
            ->merge(
                Transaction::query()->where('transactions.account_id', $accountId)->whereNotNull('transactions.beneficiary_id')
                    ->join('beneficiaries', 'beneficiaries.id', '=', 'transactions.beneficiary_id')
                    ->pluck('beneficiaries.beneficiary_account')
            )->unique()->take(self::MAX_FANOUT);

        foreach ($targets as $number) {
            $internal = Account::query()->where('account_number', $number)->first();
            if ($internal) {
                $out[] = ['account', $this->accountKey($internal), $internal->account_number, $internal->id, 'transfers_to', ['status' => $internal->status?->value]];
            } else {
                $out[] = ['beneficiary', 'beneficiary:'.$number, $number, $number, 'beneficiary', []];
            }
        }

        // Accounts that list this account as their beneficiary.
        $senders = Beneficiary::query()->where('beneficiary_account', Account::query()->whereKey($accountId)->value('account_number'))
            ->pluck('account_id')->unique()->take(self::MAX_FANOUT);
        foreach (Account::query()->whereIn('id', $senders)->get() as $sender) {
            $out[] = ['account', $this->accountKey($sender), $sender->account_number, $sender->id, 'transfers_to', ['status' => $sender->status?->value]];
        }

        return $out;
    }

    private function deviceNeighbours(int $deviceId): array
    {
        $out = [];
        foreach (Account::query()->whereIn('id', $this->accountIdsForDevice($deviceId))->limit(self::MAX_FANOUT)->get() as $account) {
            $out[] = ['account', $this->accountKey($account), $account->account_number, $account->id, 'uses', ['status' => $account->status?->value]];
        }

        return $out;
    }

    private function ipNeighbours(string $ip): array
    {
        $ids = Transaction::query()->where('ip_address', $ip)->distinct()->limit(self::MAX_FANOUT)->pluck('account_id')
            ->merge(AccountEvent::query()->where('ip_address', $ip)->distinct()->limit(self::MAX_FANOUT)->pluck('account_id'))
            ->unique();

        $out = [];
        foreach (Account::query()->whereIn('id', $ids)->get() as $account) {
            $out[] = ['account', $this->accountKey($account), $account->account_number, $account->id, 'ip', ['status' => $account->status?->value]];
        }

        return $out;
    }

    private function beneficiaryNeighbours(string $number): array
    {
        $ids = Beneficiary::query()->where('beneficiary_account', $number)->distinct()->limit(self::MAX_FANOUT)->pluck('account_id');

        $out = [];
        foreach (Account::query()->whereIn('id', $ids)->get() as $account) {
            $out[] = ['account', $this->accountKey($account), $account->account_number, $account->id, 'beneficiary', ['status' => $account->status?->value]];
        }

        return $out;
    }

    /**
     * @return array<int, int>
     */
    private function deviceIdsForAccount(Account $account): array
    {
        return Transaction::query()->where('account_id', $account->id)->whereNotNull('device_id')->pluck('device_id')
            ->merge(AccountEvent::query()->where('account_id', $account->id)->whereNotNull('device_id')->pluck('device_id'))
            ->merge(CustomerDevice::query()->where('customer_id', $account->customer_id)->pluck('device_id'))
            ->unique()->values()->all();
    }

    /**
     * @return array<int, int>
     */
    private function accountIdsForDevice(int $deviceId): array
    {
        $customerIds = CustomerDevice::query()->where('device_id', $deviceId)->pluck('customer_id');

        return Transaction::query()->where('device_id', $deviceId)->distinct()->pluck('account_id')
            ->merge(AccountEvent::query()->where('device_id', $deviceId)->distinct()->pluck('account_id'))
            ->merge(Account::query()->whereIn('customer_id', $customerIds)->pluck('id'))
            ->unique()->values()->all();
    }

    /**
     * @return Collection<int, string>
     */
    private function ipsForAccount(int $accountId): Collection
    {
        return Transaction::query()->where('account_id', $accountId)->whereNotNull('ip_address')->distinct()->limit(self::MAX_FANOUT)->pluck('ip_address')
            ->merge(AccountEvent::query()->where('account_id', $accountId)->whereNotNull('ip_address')->distinct()->limit(self::MAX_FANOUT)->pluck('ip_address'))
            ->unique()->values();
    }

    /**
     * @param  array<string, array>  $nodes
     * @param  array<int, int>  $accountIds
     */
    private function annotateRisk(array &$nodes, array $accountIds): void
    {
        if ($accountIds === []) {
            return;
        }

        $risk = RiskScore::query()
            ->join('transactions', 'transactions.id', '=', 'risk_scores.transaction_id')
            ->whereIn('transactions.account_id', $accountIds)
            ->groupBy('transactions.account_id')
            ->selectRaw('transactions.account_id as account_id, max(risk_scores.score) as max_score')
            ->pluck('max_score', 'account_id');

        $eventRisk = RiskScore::query()
            ->join('account_events', 'account_events.id', '=', 'risk_scores.event_id')
            ->whereIn('account_events.account_id', $accountIds)
            ->groupBy('account_events.account_id')
            ->selectRaw('account_events.account_id as account_id, max(risk_scores.score) as max_score')
            ->pluck('max_score', 'account_id');

        foreach ($accountIds as $id) {
            $key = 'account:'.$id;
            if (isset($nodes[$key])) {
                $nodes[$key]['data']['risk'] = (int) max($risk[$id] ?? 0, $eventRisk[$id] ?? 0);
            }
        }
    }

    private function accountKey(Account $account): string
    {
        return 'account:'.$account->id;
    }
}
