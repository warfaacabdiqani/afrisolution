<?php
return [
    'profiles' => [
        'clinic' => [
            'widgets' => ['total_patients', 'today_appointments', 'active_doctors', 'monthly_revenue'],
            'sections' => ['appointments', 'recent_customers', 'calendar', 'business_information'],
            'quick_actions' => ['add_patient', 'book_appointment', 'create_prescription'],
        ],
        'beauty-salon' => [
            'widgets' => ['staff_count', 'branch_count', 'subscription', 'monthly_revenue'],
            'sections' => ['business_information'], 'quick_actions' => [],
        ],
        'stadium' => [
            'widgets' => ['staff_count', 'branch_count', 'subscription', 'monthly_revenue'],
            'sections' => ['business_information'], 'quick_actions' => [],
        ],
    ],
    'widgets' => [
        'total_patients' => ['label' => 'Total Patients', 'format' => 'number', 'module' => 'patients', 'icon' => 'members', 'tone' => 'mint'],
        'today_appointments' => ['label' => "Today's Appointments", 'format' => 'number', 'module' => 'appointments', 'icon' => 'calendar', 'tone' => 'blue'],
        'active_doctors' => ['label' => 'Active Doctors', 'format' => 'number', 'module' => 'doctors', 'icon' => 'doctor', 'tone' => 'blue'],
        'monthly_revenue' => ['label' => 'Monthly Revenue', 'format' => 'currency', 'module' => 'billing', 'icon' => 'revenue', 'tone' => 'mint'],
        'staff_count' => ['label' => 'Staff Members', 'format' => 'number', 'module' => 'staff', 'icon' => 'members', 'tone' => 'mint'],
        'branch_count' => ['label' => 'Locations', 'format' => 'number', 'module' => 'dashboard', 'icon' => 'branch', 'tone' => 'blue'],
        'subscription' => ['label' => 'Plan', 'format' => 'text', 'module' => 'dashboard', 'icon' => 'roles', 'tone' => 'amber'],
    ],
    // Invoice creation is a placeholder in this release, so it has no quick action.
    'quick_actions' => [
        'add_patient' => ['label' => 'Add Patient', 'description' => 'Register a new patient', 'icon' => 'patient', 'tone' => 'mint', 'module' => 'patients', 'permission' => 'patients.create', 'to' => '/app/patients/create'],
        'book_appointment' => ['label' => 'Book Appointment', 'description' => 'Schedule a visit', 'icon' => 'calendar', 'tone' => 'blue', 'module' => 'appointments', 'permission' => 'appointments.create', 'to' => '/app/appointments/create'],
        'create_prescription' => ['label' => 'Create Prescription', 'description' => 'Prescribe medication', 'icon' => 'audit', 'tone' => 'violet', 'module' => 'prescriptions', 'permission' => 'prescriptions.create', 'to' => '/app/prescriptions/create'],
    ],
];
