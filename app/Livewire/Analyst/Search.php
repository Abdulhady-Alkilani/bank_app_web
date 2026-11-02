<?php

namespace App\Livewire\Analyst;

use App\Models\Account;
use App\Models\AccountEvent;
use App\Models\Alert;
use App\Models\Beneficiary;
use App\Models\Device;
use App\Models\Transaction;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;

class Search extends Component
{
    #[Url(except: '')]
    public string $q = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Alert::class);
    }

    public function render(): View
    {
        $term = trim($this->q);
        $results = ['accounts' => collect(), 'devices' => collect(), 'ips' => collect(), 'beneficiaries' => collect(), 'transactions' => collect()];

        if (mb_strlen($term) >= 3) {
            $like = '%'.addcslashes($term, '%_\\').'%';

            $results['accounts'] = Account::query()->with('customer')->where('account_number', 'like', $like)->limit(10)->get();

            $results['devices'] = Device::query()->where('device_fingerprint', 'like', $like)->limit(10)->get()
                ->map(fn (Device $device) => [
                    'device' => $device,
                    'accounts' => $this->accountsFor(
                        Transaction::query()->where('device_id', $device->id)->pluck('account_id')
                            ->concat(AccountEvent::query()->where('device_id', $device->id)->pluck('account_id')),
                    ),
                ]);

            $ipAccounts = Transaction::query()->where('ip_address', 'like', $like)->limit(100)->get(['ip_address', 'account_id'])->toBase()
                ->merge(AccountEvent::query()->where('ip_address', 'like', $like)->limit(100)->get(['ip_address', 'account_id'])->toBase())
                ->groupBy('ip_address');
            $results['ips'] = $ipAccounts->take(10)->map(fn (Collection $rows, string $ip) => ['ip' => $ip, 'accounts' => $this->accountsFor($rows->pluck('account_id'))])->values();

            $results['beneficiaries'] = Beneficiary::query()->with('account.customer')->where('beneficiary_account', 'like', $like)->limit(10)->get();
        }

        if ($term !== '' && ctype_digit($term)) {
            $results['transactions'] = Transaction::query()->with(['account.customer', 'riskScore.alert'])->whereKey((int) $term)->get();
        }

        $total = collect($results)->sum(fn ($rows) => $rows->count());

        return view('livewire.analyst.search', [
            'term' => $term,
            'results' => $results,
            'total' => $total,
        ])->title(__('analyst.search.title'));
    }

    /**
     * @param  Collection<int, int>  $ids
     * @return Collection<int, Account>
     */
    private function accountsFor(Collection $ids): Collection
    {
        return Account::query()->with('customer')->whereIn('id', $ids->filter()->unique())->limit(10)->get();
    }
}
