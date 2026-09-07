<?php

return [
    'modules' => [
        'dashboard' => ['Dashboard', 'dashboard.view', null, 'dashboard', ''],
        'patients' => ['Patients', 'patients.view', 'patient_management', 'patient', 'Patient care'],
        'appointments' => ['Appointments', 'appointments.view', 'appointments', 'calendar', 'Patient care'],
        'doctors' => ['Doctors / Clinicians', 'doctors.view', 'clinicians', 'doctor', 'Clinical'],
        'consultations' => ['Consultations', 'consultations.view', 'emr', 'activity', 'Clinical'],
        'prescriptions' => ['Prescriptions', 'prescriptions.view', 'prescriptions', 'audit', 'Clinical'],
        'pharmacy' => ['Pharmacy', 'pharmacy.view', 'pharmacy', 'storage', 'Operations'],
        'billing' => ['Billing', 'billing.view', 'billing', 'revenue', 'Operations'],
        'reports' => ['Reports', 'reports.view', 'basic_reports', 'activity', 'Analytics'],
        'staff' => ['Users / Staff', 'staff.view', null, 'members', 'Administration'],
        'settings' => ['Clinic Settings', 'clinic_settings.view', null, 'settings', 'Administration'],
    ],
    'roles' => [
        'owner' => ['*'], 'admin' => ['*'],
        'staff' => ['dashboard.view'],
        'receptionist' => ['dashboard.view', 'patients.view', 'patients.create', 'patients.update', 'appointments.view', 'appointments.create', 'appointments.update', 'appointments.cancel'],
        'doctor' => ['dashboard.view', 'patients.view', 'appointments.view', 'consultations.view', 'consultations.create', 'consultations.update', 'prescriptions.view', 'prescriptions.create'],
        'nurse' => ['dashboard.view', 'patients.view', 'appointments.view', 'consultations.view'],
        'pharmacist' => ['dashboard.view', 'pharmacy.view', 'pharmacy.manage', 'prescriptions.view'],
        'cashier' => ['dashboard.view', 'billing.view', 'billing.create', 'billing.payments'],
        'management' => ['dashboard.view', 'reports.view', 'staff.view'],
    ],
];
