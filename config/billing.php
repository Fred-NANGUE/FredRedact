<?php

return [

    // Nom (colonne subscription_plans.nom) de l'offre gratuite attribuée automatiquement
    'free_plan_name' => env('BILLING_FREE_PLAN', 'Free'),

    // Minimum de tokens exigé par le middleware CheckTokens si aucun paramètre n'est donné
    'default_min_tokens' => (int) env('BILLING_DEFAULT_MIN_TOKENS', 500),

    // true  : en changeant d'offre, l'utilisateur garde les tokens restants (ils s'ajoutent au nouveau lot)
    // false : les tokens restants de l'ancienne offre sont retirés avant la nouvelle allocation
    'carry_over_on_change' => (bool) env('BILLING_CARRY_OVER', true),

    // Mode démo : un abonnement payant est activé immédiatement, sans attendre la confirmation du paiement.
    // À laisser à false en production (la confirmation passe alors par l'admin ou un webhook).
    'auto_confirm_payments' => (bool) env('BILLING_AUTO_CONFIRM', false),
];
