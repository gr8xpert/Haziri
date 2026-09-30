<?php

declare(strict_types=1);

/*
| Tenant permissions (`module.action`) and default role grants (spec §8).
| Seeded into every tenant by RolesAndPermissionsSeeder. `*` = every permission.
*/
return [
    'permissions' => [
        'attendance.view_all', 'attendance.view_team', 'attendance.view_own',
        'attendance.manual_entry', 'attendance.manual_edit', 'attendance.override_status', 'attendance.recompute',
        'employees.view', 'employees.manage', 'employees.import',
        'organisation.manage', 'shifts.manage', 'holidays.manage',
        'devices.manage', 'usb.import', 'kiosk.manage',
        'leave.request', 'leave.request_on_behalf', 'leave.approve_first', 'leave.approve_final',
        'leave.manage_types', 'leave.adjust_balance',
        'corrections.request', 'corrections.approve_first', 'corrections.approve_final', 'od_wfh.request_team',
        'periods.lock', 'periods.unlock',
        'reports.view', 'reports.view_team', 'reports.export',
        'payroll.manage', 'visitors.manage', 'announcements.manage',
        'users.manage', 'settings.manage', 'billing.manage', 'audit.view', 'data.delete',
    ],

    'roles' => [
        'owner' => ['*'],
        'admin' => ['*', '-billing.manage', '-data.delete'],
        'hr' => [
            'attendance.view_all', 'attendance.manual_entry', 'attendance.manual_edit', 'attendance.override_status',
            'attendance.recompute', 'employees.view', 'employees.manage', 'employees.import', 'organisation.manage',
            'shifts.manage', 'holidays.manage', 'usb.import', 'leave.request', 'leave.request_on_behalf',
            'leave.approve_final', 'leave.manage_types', 'leave.adjust_balance', 'corrections.request',
            'corrections.approve_final', 'periods.lock', 'reports.view', 'reports.export', 'payroll.manage',
            'announcements.manage', 'audit.view',
        ],
        'manager' => [
            'attendance.view_team', 'attendance.view_own', 'employees.view', 'leave.request', 'leave.approve_first',
            'corrections.request', 'corrections.approve_first', 'od_wfh.request_team', 'reports.view_team',
        ],
        'receptionist' => [
            'attendance.view_all', 'attendance.manual_entry', 'employees.view', 'usb.import', 'visitors.manage',
            'kiosk.manage',
        ],
        'employee' => ['attendance.view_own', 'leave.request', 'corrections.request'],
    ],
];
