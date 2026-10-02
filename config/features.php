<?php

declare(strict_types=1);

/*
| Sellable features, switched per plan and per client by the super-admin (spec §3.3).
| Attendance itself (employees, punches, shifts, reports) is the core product and is always on.
|
| requires — features that must be on for this one to work. Turning a feature on turns these on;
|            turning one of these off turns this feature off (the UI says so first).
| default  — value for a client that has never had the switch set (keeps features that existed before
|            their switch was introduced working for existing clients).
*/
return [
    'groups' => [
        'attendance' => ['label' => 'Attendance', 'description' => 'Core attendance is always on. Optional parts:'],
        'payroll_lite' => ['label' => 'Payroll Lite', 'description' => 'Salary from attendance, payslips, advances, bank file.'],
        'payroll_full' => ['label' => 'Payroll Full', 'description' => 'Statutory payroll on top of Payroll Lite.'],
        'addons' => ['label' => 'Add-ons', 'description' => 'Integrations and branding.'],
    ],

    'features' => [
        // Attendance
        'machine_sync' => ['group' => 'attendance', 'label' => 'Fingerprint / face machines', 'requires' => [], 'default' => false],
        'kiosk' => ['group' => 'attendance', 'label' => 'Kiosk tablet', 'requires' => [], 'default' => false],
        'kiosk_face' => ['group' => 'attendance', 'label' => 'Face recognition on kiosk', 'requires' => ['kiosk'], 'default' => false],
        'mobile_punch' => ['group' => 'attendance', 'label' => 'Mobile punch (GPS)', 'requires' => [], 'default' => false],
        'leave' => ['group' => 'attendance', 'label' => 'Leave management', 'requires' => [], 'default' => false],
        'rosters' => ['group' => 'attendance', 'label' => 'Rosters & shift swaps', 'requires' => [], 'default' => true],
        'multi_branch' => ['group' => 'attendance', 'label' => 'Branch managers & branch dashboard', 'requires' => [], 'default' => false],
        'visitors' => ['group' => 'attendance', 'label' => 'Visitor log', 'requires' => [], 'default' => false],
        'ai_reports' => ['group' => 'attendance', 'label' => 'AI assistant & anomaly detection', 'requires' => [], 'default' => false],

        // Payroll Lite
        'payroll_lite' => ['group' => 'payroll_lite', 'label' => 'Payroll Lite (salary, payslips, advances, bank file)', 'requires' => [], 'default' => false],

        // Payroll Full — every part builds on Payroll Lite
        'payroll_tax' => ['group' => 'payroll_full', 'label' => 'Income tax (FBR slabs)', 'requires' => ['payroll_lite'], 'default' => false],
        'payroll_social' => ['group' => 'payroll_full', 'label' => 'EOBI & social security', 'requires' => ['payroll_lite'], 'default' => false],
        'payroll_pf' => ['group' => 'payroll_full', 'label' => 'Provident fund', 'requires' => ['payroll_lite'], 'default' => false],
        'payroll_gratuity' => ['group' => 'payroll_full', 'label' => 'Gratuity', 'requires' => ['payroll_lite'], 'default' => false],
        'payroll_adjustments' => ['group' => 'payroll_full', 'label' => 'Bonuses, arrears & one-off pay items', 'requires' => ['payroll_lite'], 'default' => false],
        'payroll_settlement' => ['group' => 'payroll_full', 'label' => 'Final settlement', 'requires' => ['payroll_lite'], 'default' => false],

        // Add-ons
        'whatsapp' => ['group' => 'addons', 'label' => 'WhatsApp alerts', 'requires' => [], 'default' => false],
        'public_api' => ['group' => 'addons', 'label' => 'Public API & webhooks', 'requires' => [], 'default' => false],
        'white_label' => ['group' => 'addons', 'label' => 'White label (own domain, logo, colours, name)', 'requires' => [], 'default' => false],
    ],
];
