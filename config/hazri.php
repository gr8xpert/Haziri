<?php

declare(strict_types=1);

return [

    /*
    | shared    — many tenants on *.{central_domain}
    | dedicated — single-tenant instance; the root domain maps straight to HAZRI_DEDICATED_TENANT (spec §3.2)
    */
    'mode' => env('HAZRI_MODE', 'shared'),
    'dedicated_tenant' => env('HAZRI_DEDICATED_TENANT'),

    'central_domain' => env('HAZRI_CENTRAL_DOMAIN', 'hazri.localhost'),
    // Product name shown to clients without white-label.
    'platform_name' => env('APP_NAME', 'Haziri'),
    // A second domain with no platform name in it, pointing at the same server. White-label clients see it instead of
    // ours: CNAME target for their own domain, and adms.<it> / api.<it> for machines and the sync agent
    // (those endpoints find the company by device serial / agent key, so any host works). Empty = our domains.
    'neutral_domain' => env('HAZRI_NEUTRAL_DOMAIN'),
    'admin_domain' => env('HAZRI_ADMIN_DOMAIN', 'admin.' . env('HAZRI_CENTRAL_DOMAIN', 'hazri.localhost')),
    'adms_domain' => env('HAZRI_ADMS_DOMAIN', 'adms.' . env('HAZRI_CENTRAL_DOMAIN', 'hazri.localhost')),
    'api_domain' => env('HAZRI_API_DOMAIN', 'api.' . env('HAZRI_CENTRAL_DOMAIN', 'hazri.localhost')),

    // Where the landing page contact form sends enquiries.
    'contact_email' => env('HAZRI_CONTACT_EMAIL', 'gr8xpert@gmail.com'),

    /*
    | The public demo company (hazri:demo:reset rebuilds it every night). Messages from it are logged, never sent,
    | its logins can't be changed, and the login page shows them. Empty = no public demo.
    */
    'demo' => [
        'tenant' => env('HAZRI_DEMO_TENANT'),
        'company' => env('HAZRI_DEMO_COMPANY', 'KunSystems'),
        'password' => env('HAZRI_DEMO_PASSWORD', 'password'),
        'ai_token_budget' => (int) env('HAZRI_DEMO_AI_TOKEN_BUDGET', 200_000),
    ],

    'tenant_db_prefix' => 'hazri_t_',
    'default_timezone' => 'Asia/Karachi',

    /* Tenant rule parameters and their defaults (spec §7.4). Stored per tenant in the `settings` table. */
    'settings_defaults' => [
        'dedupe_window_sec' => 60,
        'trust_device_direction' => false,
        'pay_cycle_start_day' => 1,          // 21 → the month runs 21st → 20th
        'leave_year_start_month' => 1,       // 10 → leave year Oct → Sep
        'lates_per_absent' => 3,
        'late_adjustment_mode' => 'salary',  // salary|leave — where late roll-ups and half days are deducted
        'late_adjustment_leave_type' => null, // leave type code charged in "leave" mode
        'late_count_resets' => 'monthly',
        'half_day_counts_as' => 0.5,
        'absent_deduction_days' => 1,
        'unpaid_leave_deduction_days' => 1,
        'overtime_enabled' => false,
        'overtime_rounding_min' => 15,
        // Payroll Full (verify statutory figures each budget; all editable under Payroll → Payroll rules)
        'tax_year_start_month' => 7,              // Pakistan: July – June
        'tax_surcharge_threshold' => 10000000,    // annual income above which the surcharge applies
        'tax_surcharge_percent' => 9,             // % of the tax
        'tax_surcharge_last_year' => 2025,        // last tax year with the surcharge (abolished by Finance Act 2026)
        'eobi_wage_base' => 37000,                // contributions are on the minimum wage
        'eobi_employee_percent' => 1,
        'eobi_employer_percent' => 5,
        'social_security_wage_base' => 37000,     // PESSI / SESSI
        'social_security_percent' => 6,           // employer only
        'pf_employee_percent' => 8.33,            // of basic
        'pf_employer_percent' => 8.33,
        'gratuity_basis' => 'basic',              // basic|gross
        'gratuity_days_per_year' => 30,           // days' pay per year of service
        'gratuity_min_years' => 1,
        'gratuity_part_year_months' => 6,         // a part year of at least this many months counts as a year
        'punch_search_window_before_min' => 240,
        'punch_search_window_after_min' => 360,
        'auto_absent_after_days' => 1,
        'store_punch_photos' => true,
        'photo_retention_days' => 90,
        'geofence_default_radius_m' => 150,
        'mobile_max_accuracy_m' => 0,         // reject mobile punches with a worse GPS fix (0 = off)
        'mobile_require_selfie' => false,
        'face_match_threshold' => 0.6,        // kiosk face recognition: minimum similarity (0..1)
        'face_match_margin' => 0.05,          // … and how far ahead of the next closest person
        'face_check_photo_punches' => false,  // kiosk PIN / QR and phone punches: check the photo's face (kiosk_face); fail → approval
        'allow_future_manual_punch' => false,
        'employee_can_see_others' => false,
        'leave_approval_chain' => ['manager', 'hr'],
        'correction_approval_chain' => ['manager', 'hr'],
        // Notifications (spec §11). notify.{event} = [channels] overrides the per-event defaults.
        'daily_summary_at' => '18:00',
        'absent_alert_at' => '11:00',
        'quiet_hours' => ['22:00', '08:00'],
        'notify_employee_late' => false,
    ],

    'ai' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-opus-5-5'),
        'readonly_db_username' => env('AI_DB_READONLY_USERNAME'),
        'readonly_db_password' => env('AI_DB_READONLY_PASSWORD'),
    ],

    'billing' => [
        // New companies start on a trial; when it ends they are suspended until the super-admin activates them.
        'trial_days' => (int) env('HAZRI_TRIAL_DAYS', 15),
        'due_days' => (int) env('HAZRI_BILLING_DUE_DAYS', 10),
        'grace_days' => (int) env('HAZRI_BILLING_GRACE_DAYS', 15),
        // Shown to company owners on the Billing page (spec §4.1: manual bank / JazzCash / Easypaisa).
        'payment_instructions' => env('HAZRI_PAYMENT_INSTRUCTIONS', 'Bank: Meezan Bank · Title: Haziri · IBAN: PKxx MEZN xxxx xxxx xxxx xxxx · JazzCash / Easypaisa: 03xx-xxxxxxx. Put the invoice number in the reference.'),
        // The same for white-label clients: must not name the platform (no account title by default).
        'payment_instructions_white_label' => env('HAZRI_PAYMENT_INSTRUCTIONS_WHITE_LABEL', 'Bank: Meezan Bank · IBAN: PKxx MEZN xxxx xxxx xxxx xxxx · JazzCash / Easypaisa: 03xx-xxxxxxx. Put the invoice number in the reference.'),
    ],

    'notifications' => [
        'whatsapp_provider' => env('WHATSAPP_PROVIDER', 'log'),
        'sms_provider' => env('SMS_PROVIDER', 'log'),
    ],
];
