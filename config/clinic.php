<?php

return [
    'doctor_permissions' => ['doctors.view','doctors.create','doctors.update','doctors.deactivate','doctors.schedule.view','doctors.schedule.update','doctors.leave.manage','doctors.specialties.manage'],
    'patient_permissions' => ['patients.view', 'patients.create', 'patients.update', 'patients.archive', 'patients.restore', 'patients.documents.view', 'patients.documents.upload', 'patients.documents.delete', 'patients.medical_history.view', 'patients.medical_history.update'],
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
        'receptionist' => ['doctors.view', 'doctors.schedule.view', 'dashboard.view', 'patients.view', 'patients.create', 'patients.update', 'appointments.view', 'appointments.create', 'appointments.update', 'appointments.cancel'],
        'doctor' => ['doctors.view', 'doctors.schedule.view', 'dashboard.view', 'patients.view', 'patients.medical_history.view', 'patients.medical_history.update', 'patients.documents.view', 'patients.documents.upload', 'appointments.view', 'consultations.view', 'consultations.create', 'consultations.update', 'prescriptions.view', 'prescriptions.create'],
        'nurse' => ['dashboard.view', 'patients.view', 'patients.medical_history.view', 'patients.medical_history.update', 'appointments.view', 'consultations.view'],
        'pharmacist' => ['dashboard.view', 'pharmacy.view', 'pharmacy.manage', 'prescriptions.view'],
        'cashier' => ['dashboard.view', 'billing.view', 'billing.create', 'billing.payments'],
        'management' => ['doctors.view', 'doctors.schedule.view', 'dashboard.view', 'reports.view', 'staff.view'],
    ],
];
