<?php

return [
    'settings_permissions' => ['clinic_settings.view','clinic_settings.update', ...array_map(fn($section)=>'clinic_settings.'.$section.'.'.($section==='branches'?'manage':'update'), ['general','branding','branches','patients','appointments','clinical','pharmacy','billing','notifications','documents','security']), 'clinic_settings.subscription.view'],
    'prescription_permissions' => ['prescriptions.view','prescriptions.create','prescriptions.update','prescriptions.cancel','prescriptions.print','prescriptions.dispense','prescriptions.view_all_doctors','prescriptions.medications.manage'],
    'appointment_permissions' => ['appointments.view','appointments.view_all','appointments.create','appointments.update','appointments.reschedule','appointments.cancel','appointments.check_in','appointments.start_consultation','appointments.complete','appointments.override_schedule','appointments.types.manage'],
    'doctor_permissions' => ['doctors.view','doctors.create','doctors.update','doctors.deactivate','doctors.schedule.view','doctors.schedule.update','doctors.leave.manage','doctors.specialties.manage'],
    'patient_permissions' => ['patients.view', 'patients.create', 'patients.update', 'patients.archive', 'patients.restore', 'patients.documents.view', 'patients.documents.upload', 'patients.documents.delete', 'patients.medical_history.view', 'patients.medical_history.update'],
    // Additional capabilities protect workspace implementations that remain clinical.
    'business_module_map' => [
        'dashboard' => ['dashboard'], 'patients' => ['customers', 'patients'],
        'appointments' => ['bookings', 'clinical'], 'doctors' => ['clinical'],
        'consultations' => ['clinical'], 'prescriptions' => ['prescriptions'],
        'pharmacy' => ['pharmacy'], 'billing' => ['billing'], 'reports' => ['reports'],
        'staff' => ['staff'], 'settings' => ['settings'], 'support' => ['support'],
    ],
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
        'support' => ['Help & Support', 'support.view', null, 'roles', 'Administration'],
        'settings' => ['Clinic Settings', 'clinic_settings.view', null, 'settings', 'Administration'],
    ],
    'roles' => [
        'owner' => ['*', 'support.view', 'support.tickets.create', 'support.tickets.view_own', 'support.tickets.view_clinic'],
        'admin' => ['*', 'support.view', 'support.tickets.create', 'support.tickets.view_own', 'support.tickets.view_clinic'],
        'staff' => ['dashboard.view', 'support.view', 'support.tickets.create', 'support.tickets.view_own'],
        'receptionist' => ['doctors.view', 'doctors.schedule.view', 'dashboard.view', 'patients.view', 'patients.create', 'patients.update', 'appointments.view', 'appointments.view_all', 'appointments.create', 'appointments.update', 'appointments.reschedule', 'appointments.cancel', 'appointments.check_in', 'support.view', 'support.tickets.create', 'support.tickets.view_own'],
        'doctor' => ['doctors.view', 'doctors.schedule.view', 'dashboard.view', 'patients.view', 'patients.medical_history.view', 'patients.medical_history.update', 'patients.documents.view', 'patients.documents.upload', 'appointments.view', 'appointments.start_consultation', 'appointments.complete', 'consultations.view', 'consultations.create', 'consultations.update', 'prescriptions.view', 'prescriptions.create', 'prescriptions.update', 'prescriptions.cancel', 'prescriptions.print', 'support.view', 'support.tickets.create', 'support.tickets.view_own'],
        'nurse' => ['dashboard.view', 'patients.view', 'patients.medical_history.view', 'patients.medical_history.update', 'appointments.view', 'appointments.view_all', 'appointments.check_in', 'consultations.view', 'support.view', 'support.tickets.create', 'support.tickets.view_own'],
        'pharmacist' => ['dashboard.view', 'pharmacy.view', 'pharmacy.manage', 'prescriptions.view', 'prescriptions.view_all_doctors', 'prescriptions.dispense', 'prescriptions.print', 'support.view', 'support.tickets.create', 'support.tickets.view_own'],
        'cashier' => ['dashboard.view', 'billing.view', 'billing.create', 'billing.payments', 'support.view', 'support.tickets.create', 'support.tickets.view_own'],
        'management' => ['doctors.view', 'doctors.schedule.view', 'dashboard.view', 'reports.view', 'staff.view', 'support.view', 'support.tickets.create', 'support.tickets.view_own', 'support.tickets.view_clinic'],
    ],
];
