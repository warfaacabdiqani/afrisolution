<?php

return [
    // Only registered server-side adapters can supply charges to the ledger.
    'sources' => [
        'dental_treatment' => App\Services\Billing\DentalTreatmentBillingAdapter::class,
        'clinic_appointment' => App\Services\Billing\ClinicAppointmentBillingAdapter::class,
        'clinic_appointment_deposit' => App\Services\Billing\ClinicAppointmentDepositAdapter::class,
        'salon_appointment' => App\Services\Billing\SalonBillingAdapter::class,
        'salon_appointment_deposit' => App\Services\Billing\SalonDepositAdapter::class,
        'dental_plan_deposit' => App\Services\Billing\DentalPlanDepositAdapter::class,
    ],
    'customers' => [
        'patient' => ['model' => App\Models\Patient::class, 'column' => 'patient_id', 'capability' => 'clinical'],
        'salon_client' => ['model' => App\Models\SalonClient::class, 'column' => 'salon_client_id', 'capability' => 'salon_core', 'branch_column' => 'branch_id'],
    ],
];
