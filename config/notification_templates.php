<?php

declare(strict_types=1);

/*
| Default notification texts (spec §11), copied into each tenant's notification_templates on first use,
| where HR can edit them. {{ placeholders }} are filled from the event data. Urdu variants are sent to users with locale "ur".
| 'channels' = default channels for the event (tenant setting notify.{event} overrides).
*/
return [
    'leave_requested' => [
        'channels' => ['mail', 'database', 'whatsapp'],
        'en' => ['Leave request from {{ employee }}', '{{ employee }} applied for {{ type }} leave: {{ from }} to {{ to }} ({{ days }} day(s)). Reason: {{ reason }}. Please approve or reject.'],
        'ur' => ['{{ employee }} کی چھٹی کی درخواست', '{{ employee }} نے {{ from }} سے {{ to }} تک {{ type }} چھٹی ({{ days }} دن) کی درخواست دی ہے۔ وجہ: {{ reason }}۔ براہ کرم منظور یا مسترد کریں۔'],
    ],
    'leave_decided' => [
        'channels' => ['mail', 'database', 'whatsapp'],
        'en' => ['Your leave was {{ status }}', 'Your {{ type }} leave from {{ from }} to {{ to }} was {{ status }}. {{ note }}'],
        'ur' => ['آپ کی چھٹی {{ status }}', 'آپ کی {{ from }} سے {{ to }} تک کی {{ type }} چھٹی {{ status }} ہو گئی ہے۔ {{ note }}'],
    ],
    'correction_requested' => [
        'channels' => ['mail', 'database'],
        'en' => ['Attendance correction from {{ employee }}', '{{ employee }} asked for "{{ type }}" on {{ date }}. Reason: {{ reason }}.'],
        'ur' => ['{{ employee }} کی حاضری درستگی', '{{ employee }} نے {{ date }} کے لیے "{{ type }}" کی درخواست دی ہے۔ وجہ: {{ reason }}۔'],
    ],
    'correction_decided' => [
        'channels' => ['mail', 'database'],
        'en' => ['Attendance correction {{ status }}', 'Your "{{ type }}" request for {{ date }} was {{ status }}.'],
        'ur' => ['حاضری درستگی {{ status }}', 'آپ کی {{ date }} کی "{{ type }}" درخواست {{ status }} ہو گئی ہے۔'],
    ],
    'device_offline' => [
        'channels' => ['mail', 'database'],
        'en' => ['Attendance machine offline: {{ device }}', '{{ device }} ({{ serial }}) has not contacted Hazri since {{ last_seen }}. Check power and internet.'],
        'ur' => ['حاضری مشین آف لائن: {{ device }}', '{{ device }} ({{ serial }}) کا {{ last_seen }} سے رابطہ نہیں ہوا۔ بجلی اور انٹرنیٹ چیک کریں۔'],
    ],
    'unmapped_pins_pending' => [
        'channels' => ['database'],
        'en' => ['{{ count }} unmapped machine PIN(s)', 'Punches from {{ count }} PIN(s) are not linked to any employee and are not counted. Map them under Devices → Unmapped PINs.'],
        'ur' => ['{{ count }} نامعلوم مشین PIN', '{{ count }} PIN کی حاضری کسی ملازم سے منسلک نہیں۔ Devices → Unmapped PINs میں منسلک کریں۔'],
    ],
    'daily_summary' => [
        'channels' => ['mail'],
        'en' => ['Attendance summary {{ date }}', 'Present: {{ present }} · Late: {{ late }} · Absent: {{ absent }} · On leave: {{ on_leave }} · Missing punch-out: {{ missing_out }}.'],
        'ur' => ['حاضری خلاصہ {{ date }}', 'حاضر: {{ present }} · تاخیر: {{ late }} · غیر حاضر: {{ absent }} · چھٹی: {{ on_leave }} · آؤٹ پنچ غائب: {{ missing_out }}۔'],
    ],
    'employee_absent_no_leave' => [
        'channels' => ['database', 'whatsapp'],
        'en' => ['Not in yet: {{ count }} team member(s)', 'By {{ time }} these team members have not punched in and have no leave: {{ names }}.'],
        'ur' => ['ابھی تک نہیں آئے: {{ count }}', '{{ time }} تک ان افراد نے حاضری نہیں لگائی اور چھٹی بھی نہیں: {{ names }}۔'],
    ],
    'employee_late' => [
        'channels' => ['database'],
        'en' => ['{{ employee }} arrived late', '{{ employee }} punched in at {{ time }} ({{ minutes }} min late).'],
        'ur' => ['{{ employee }} تاخیر سے آئے', '{{ employee }} نے {{ time }} پر حاضری لگائی ({{ minutes }} منٹ تاخیر)۔'],
    ],
    'missing_punch_out' => [
        'channels' => ['database', 'whatsapp', 'sms'],
        'en' => ['You forgot to punch out', 'Hi {{ name }}, there is no punch-out for {{ date }}. Please punch out or request a correction.'],
        'ur' => ['آپ آؤٹ پنچ بھول گئے', '{{ name }}، {{ date }} کا آؤٹ پنچ موجود نہیں۔ براہ کرم پنچ کریں یا درستگی کی درخواست دیں۔'],
    ],
    'period_locked' => [
        'channels' => ['mail', 'database'],
        'en' => ['{{ month }} attendance locked', 'Attendance for {{ month }} has been locked by {{ by }}. Deduction days: {{ deduction_days }}.'],
        'ur' => ['{{ month }} کی حاضری لاک', '{{ month }} کی حاضری {{ by }} نے لاک کر دی ہے۔ کٹوتی کے دن: {{ deduction_days }}۔'],
    ],
    'probation_ending' => [
        'channels' => ['mail', 'database'],
        'en' => ['Probation ending: {{ employee }}', "{{ employee }}'s probation ends on {{ date }}."],
        'ur' => ['پروبیشن ختم: {{ employee }}', '{{ employee }} کا پروبیشن {{ date }} کو ختم ہو رہا ہے۔'],
    ],
    'contract_ending' => [
        'channels' => ['mail', 'database'],
        'en' => ['Contract ending: {{ employee }}', "{{ employee }}'s contract ends on {{ date }}."],
        'ur' => ['معاہدہ ختم: {{ employee }}', '{{ employee }} کا معاہدہ {{ date }} کو ختم ہو رہا ہے۔'],
    ],
    'birthday_today' => [
        'channels' => ['database'],
        'en' => ['Birthday today 🎂', "It's {{ employee }}'s birthday today."],
        'ur' => ['آج سالگرہ ہے 🎂', 'آج {{ employee }} کی سالگرہ ہے۔'],
    ],
    'payslip_ready' => [
        'channels' => ['mail', 'whatsapp'],
        'en' => ['Payslip for {{ month }}', 'Hi {{ name }}, your payslip for {{ month }} is ready. Net pay: Rs {{ net }}.'],
        'ur' => ['{{ month }} کی تنخواہ پرچی', '{{ name }}، آپ کی {{ month }} کی تنخواہ پرچی تیار ہے۔ خالص تنخواہ: {{ net }} روپے۔'],
    ],
    'broadcast' => [
        'channels' => ['database', 'mail'],
        'en' => ['{{ title }}', '{{ body }}'],
        'ur' => ['{{ title }}', '{{ body }}'],
    ],
];
