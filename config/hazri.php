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

    /* Optional modules; plans define defaults, super-admin overrides per tenant (spec §3.3). */
    'feature_flags' => [
        'machine_sync', 'kiosk', 'mobile_punch', 'leave', 'payroll_lite', 'visitors', 'ai_reports', 'whatsapp',
        'public_api', 'white_label',
    ],

    /* Tenant rule parameters and their defaults (spec §7.4). Stored per tenant in the `settings` table. */
    'settings_defaults' => [
        'dedupe_window_sec' => 60,
        'trust_device_direction' => false,
        'lates_per_absent' => 3,
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

    'notifications' => [
        'whatsapp_provider' => env('WHATSAPP_PROVIDER', 'log'),
        'sms_provider' => env('SMS_PROVIDER', 'log'),
    ],
];
