<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Role-grouped sidebar navigation. Links whose route does not exist yet are skipped,
 * so other modules simply register a route with the conventional name to appear here.
 */
class Navigation
{
    /**
     * @return array<int, array{title: string, items: array<int, array{label: string, route: string}>}>
     */
    public static function for(User $user): array
    {
        $groups = [];

        if ($user->hasRole('admin')) {
            $groups[] = self::group('administration', [
                ['dashboard', 'admin.dashboard'], ['users', 'admin.users'], ['roles', 'admin.roles'],
                ['settings', 'admin.settings'], ['integrations', 'admin.integrations'],
            ]);
            $groups[] = self::group('detection', [
                ['rules', 'admin.rules'], ['watchlists', 'admin.watchlists'], ['models', 'admin.models'],
            ]);
            $groups[] = self::group('system', [
                ['health', 'admin.health'], ['audit', 'admin.audit'], ['backups', 'admin.backups'],
            ]);
        }

        if ($user->hasRole(['supervisor', 'admin'])) {
            $groups[] = self::group('supervision', [
                ['dashboard', 'supervisor.dashboard'], ['assignments', 'supervisor.assignments'], ['approvals', 'supervisor.approvals'],
                ['qa', 'supervisor.qa'], ['rule_suggestions', 'supervisor.rule-suggestions'], ['reports', 'supervisor.reports'],
                ['audit', 'supervisor.audit'], ['models', 'supervisor.models'],
            ]);
        }

        if ($user->hasRole(['analyst', 'supervisor', 'admin'])) {
            $groups[] = self::group('analysis', [
                ['dashboard', 'analyst.dashboard'], ['alerts', 'analyst.alerts'], ['cases', 'analyst.cases'],
                ['search', 'analyst.search'], ['assistant', 'analyst.assistant'], ['rule_suggestions', 'analyst.rule-suggestions'],
            ]);
        }

        if ($user->hasRole('customer')) {
            $groups[] = self::group('portal', [
                ['dashboard', 'portal.dashboard'], ['accounts', 'portal.accounts'], ['transfer', 'portal.transfer'],
                ['history', 'portal.history'], ['devices', 'portal.devices'], ['report_issue', 'portal.reports'],
            ]);
        }

        $groups[] = self::group('overview', [['security', 'profile.security']]);

        return array_values(array_filter($groups, fn (array $group) => $group['items'] !== []));
    }

    /**
     * @param  array<int, array{0: string, 1: string}>  $items
     * @return array{title: string, items: array<int, array{label: string, route: string}>}
     */
    private static function group(string $title, array $items): array
    {
        return [
            'title' => __('common.nav.groups.'.$title),
            'items' => collect($items)
                ->filter(fn (array $item) => Route::has($item[1]))
                ->map(fn (array $item) => ['label' => __('common.nav.'.$item[0]), 'route' => $item[1]])
                ->values()
                ->all(),
        ];
    }
}
