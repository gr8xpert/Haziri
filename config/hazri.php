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
    'admin_domain' => env('HAZRI_ADMIN_DOMAIN', 'admin.' . env('HAZRI_CENTRAL_DOMAIN', 'hazri.localhost')),
    'adms_domain' => env('HAZRI_ADMS_DOMAIN', 'adms.' . env('HAZRI_CENTRAL_DOMAIN', 'hazri.localhost')),
    'api_domain' => env('HAZRI_API_DOMAIN', 'api.' . env('HAZRI_CENTRAL_DOMAIN', 'hazri.localhost')),

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
        'punch_search_window_before_min' => 240,
        'punch_search_window_after_min' => 360,
        'auto_absent_after_days' => 1,
        'store_punch_photos' => true,
        'photo_retention_days' => 90,
        'geofence_default_radius_m' => 150,
        'mobile_max_accuracy_m' => 0,         // reject mobile punches with a worse GPS fix (0 = off)
        'mobile_require_selfie' => false,
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
        'due_days' => (int) env('HAZRI_BILLING_DUE_DAYS', 10),
        'grace_days' => (int) env('HAZRI_BILLING_GRACE_DAYS', 15),
        // Shown to company owners on the Billing page (spec §4.1: manual bank / JazzCash / Easypaisa).
        'payment_instructions' => env('HAZRI_PAYMENT_INSTRUCTIONS', 'Bank: Meezan Bank · Title: Hazri · IBAN: PKxx MEZN xxxx xxxx xxxx xxxx · JazzCash / Easypaisa: 03xx-xxxxxxx. Put the invoice number in the reference.'),
    ],

    'notifications' => [
        'whatsapp_provider' => env('WHATSAPP_PROVIDER', 'log'),
        'sms_provider' => env('SMS_PROVIDER', 'log'),
    ],
];
