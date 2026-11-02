<?php

namespace App\Mcp\Support;

use App\Models\Account;
use App\Models\Alert;
use App\Models\InvestigationCase;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Investigation\AlertQueue;
use App\Support\Masker;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Throwable;

/**
 * Authorisation, visibility and audit helpers shared by every MCP tool, resource and prompt.
 *
 * Abilities: a Sanctum token needs `mcp:read` for anything and `mcp:propose` to create proposals.
 * The token owner must be an active staff user holding the `mcp.use` permission.
 */
class McpAccess
{
    public const READ = 'mcp:read';

    public const PROPOSE = 'mcp:propose';

    /** Argument names whose values are masked in the audit trail. */
    private const SENSITIVE_KEYS = ['account_number', 'phone', 'email', 'ip', 'ip_address', 'name', 'customer_name', 'beneficiary_account'];

    private static ?int $localUserId = null;

    public static function user(?Request $request = null): ?User
    {
        $user = $request?->user() ?? auth()->user();
        if ($user instanceof User) {
            return $user;
        }

        $email = config('fraud.mcp_local_user_email');
        if (is_string($email) && $email !== '' && app()->runningInConsole()) {
            $local = User::query()->where('email', $email)->first();
            self::$localUserId = $local?->getKey();

            return $local;
        }

        return null;
    }

    /**
     * Whether the user may use MCP with the given token ability.
     */
    public static function allows(User $user, string $ability): bool
    {
        if (! $user->is_active || ! $user->isStaff() || ! $user->hasPermission('mcp.use')) {
            return false;
        }

        if (self::$localUserId !== null && self::$localUserId === $user->getKey()) {
            return true; // trusted local stdio session
        }

        return $user->tokenCan($ability);
    }

    public static function alertFor(User $user, mixed $id): Alert
    {
        $alert = is_numeric($id)
            ? Alert::query()->with(['riskScore.transaction.account.customer', 'riskScore.transaction.beneficiary', 'riskScore.event.account', 'riskScore.modelRun.model', 'assignee'])->find((int) $id)
            : null;

        if (! $alert) {
            throw McpToolException::notFound(self::bilingual('Alert not found or not accessible.', 'التنبيه غير موجود أو غير مصرح لك بالوصول إليه.'));
        }

        if (! Gate::forUser($user)->allows('view', $alert)) {
            throw McpToolException::denied(self::bilingual('Alert not found or not accessible.', 'التنبيه غير موجود أو غير مصرح لك بالوصول إليه.'));
        }

        return $alert;
    }

    public static function caseFor(User $user, mixed $id): InvestigationCase
    {
        $case = is_numeric($id) ? InvestigationCase::query()->find((int) $id) : null;
        if (! $case || ! Gate::forUser($user)->allows('view', $case)) {
            throw McpToolException::denied(self::bilingual('Case not found or not accessible.', 'الحالة غير موجودة أو غير مصرح لك بالوصول إليها.'));
        }

        return $case;
    }

    /**
     * Resolve an account from alert_id / account_id / account_number, enforcing alert visibility.
     *
     * @param  array<string, mixed>  $arguments
     */
    public static function accountFor(User $user, array $arguments): Account
    {
        if (! empty($arguments['alert_id'])) {
            $alert = self::alertFor($user, $arguments['alert_id']);
            $account = $alert->riskScore?->transaction?->account ?? $alert->riskScore?->event?->account;
            if (! $account) {
                throw McpToolException::notFound(self::bilingual('This alert has no linked account.', 'لا يوجد حساب مرتبط بهذا التنبيه.'));
            }

            return $account;
        }

        $account = match (true) {
            ! empty($arguments['account_id']) => Account::query()->with('customer')->find((int) $arguments['account_id']),
            ! empty($arguments['account_number']) => Account::query()->with('customer')->where('account_number', (string) $arguments['account_number'])->first(),
            default => throw new McpToolException(self::bilingual('Provide alert_id, account_id or account_number.', 'يجب تحديد alert_id أو account_id أو account_number.')),
        };

        if (! $account || ! self::accountVisible($user, $account)) {
            throw McpToolException::denied(self::bilingual('Account not found or not accessible.', 'الحساب غير موجود أو غير مصرح لك بالوصول إليه.'));
        }

        return $account;
    }

    /**
     * Same rule as the alert queue: an analyst cannot reach an account whose alerts are all assigned to colleagues.
     */
    public static function accountVisible(User $user, Account $account): bool
    {
        if (! $user->hasPermission('alerts.view')) {
            return false;
        }
        if ($user->hasRole(['supervisor', 'admin'])) {
            return true;
        }

        $alerts = fn () => Alert::query()->whereHas('riskScore', fn ($q) => $q
            ->whereHas('transaction', fn ($t) => $t->where('account_id', $account->id))
            ->orWhereHas('event', fn ($e) => $e->where('account_id', $account->id)));

        if (! $alerts()->exists()) {
            return true;
        }

        $visible = $alerts();
        app(AlertQueue::class)->scopeVisible($visible, $user);

        return $visible->exists();
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @param  array<string, mixed>  $detail
     */
    public static function audit(string $action, ?User $user, string $primitive, array $arguments, string $status, array $detail = []): void
    {
        try {
            app(AuditLogger::class)->log($action, null, [], [
                'primitive' => $primitive,
                'arguments' => self::maskArguments($arguments),
                'status' => $status,
                'token' => ($token = $user?->currentAccessToken()) instanceof PersonalAccessToken ? $token->name : null,
            ] + $detail, $user);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public static function maskArguments(array $arguments): array
    {
        $arguments = Masker::maskArray($arguments, self::SENSITIVE_KEYS);

        foreach ($arguments as $key => $value) {
            if (is_string($value) && mb_strlen($value) > 200) {
                $arguments[$key] = mb_substr($value, 0, 200).'...';
            }
        }

        return $arguments;
    }

    public static function bilingual(string $en, string $ar): string
    {
        return $ar.' / '.$en;
    }
}
