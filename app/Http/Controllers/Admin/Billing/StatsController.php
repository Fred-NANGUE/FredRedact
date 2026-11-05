<?php

namespace App\Http\Controllers\Admin\Billing;

use App\Http\Controllers\Controller;
use App\Models\TokenUsage;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    /** Tableau de bord administrateur. */
    public function index()
    {
        $consumption = fn () => DB::table('token_usages')
            ->where('quantite', '<', 0)
            ->whereIn('type_operation', TokenUsage::CONSUMPTION_TYPES);
        $paidSubscriptions = fn () => DB::table('subscriptions')
            ->whereIn('statut', ['actif', 'expire', 'annule', 'suspendu']);
        $revenueByCurrency = fn (bool $recent = false) => $paidSubscriptions()
            ->when($recent, fn ($query) => $query->where('created_at', '>=', now()->subDays(30)))
            ->groupBy('devise')
            ->selectRaw('devise, SUM(prix_paye) as montant')
            ->pluck('montant', 'devise')
            ->map(fn ($amount) => (float) $amount);

        return [
            'abonnements_par_plan' => DB::table('subscriptions as s')
                ->join('subscription_plans as p', 'p.id', '=', 's.plan_id')
                ->where('s.statut', 'actif')
                ->groupBy('p.id', 'p.nom')
                ->selectRaw('p.id as plan_id, p.nom, COUNT(*) as abonnes')
                ->get(),

            'abonnements_par_statut' => DB::table('subscriptions')
                ->groupBy('statut')
                ->selectRaw('statut, COUNT(*) as total')
                ->pluck('total', 'statut'),

            'revenus' => [
                'total' => (float) $paidSubscriptions()->where('devise', 'XAF')->sum('prix_paye'),
                '30_jours' => (float) $paidSubscriptions()->where('devise', 'XAF')
                    ->where('created_at', '>=', now()->subDays(30))->sum('prix_paye'),
                'total_par_devise' => $revenueByCurrency(),
                '30_jours_par_devise' => $revenueByCurrency(true),
            ],

            'tokens' => [
                'credites' => (int) DB::table('token_usages')->whereIn('type_operation', ['allocation', 'recharge'])->sum('quantite'),
                'consommes' => (int) -$consumption()->sum('quantite'),
                'par_operation' => $consumption()
                    ->groupBy('type_operation')
                    ->selectRaw('type_operation, -SUM(quantite) as tokens')
                    ->pluck('tokens', 'type_operation'),
                'par_jour_30j' => $consumption()
                    ->where('created_at', '>=', now()->subDays(30))
                    ->groupByRaw('DATE(created_at)')
                    ->orderByRaw('DATE(created_at)')
                    ->selectRaw('DATE(created_at) as jour, -SUM(quantite) as tokens')
                    ->get(),
            ],

            'top_consommateurs' => DB::table('token_usages as t')
                ->join('users as u', 'u.id', '=', 't.user_id')
                ->where('t.quantite', '<', 0)
                ->whereIn('t.type_operation', TokenUsage::CONSUMPTION_TYPES)
                ->groupBy('u.id', 'u.name', 'u.email')
                ->selectRaw('u.id, u.name, u.email, -SUM(t.quantite) as tokens')
                ->orderByDesc('tokens')
                ->limit(5)
                ->get(),
        ];
    }
}
