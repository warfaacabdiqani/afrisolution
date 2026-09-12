<?php
return [
    'statuses' => [\App\Support\PrescriptionStatus::DRAFT=>'Draft',\App\Support\PrescriptionStatus::ACTIVE=>'Active',\App\Support\PrescriptionStatus::PENDING=>'Pending',\App\Support\PrescriptionStatus::PARTIALLY_DISPENSED=>'Partially Dispensed',\App\Support\PrescriptionStatus::DISPENSED=>'Dispensed',\App\Support\PrescriptionStatus::COMPLETED=>'Completed',\App\Support\PrescriptionStatus::CANCELLED=>'Cancelled',\App\Support\PrescriptionStatus::EXPIRED=>'Expired'],
    'editable' => [\App\Support\PrescriptionStatus::DRAFT,\App\Support\PrescriptionStatus::ACTIVE,\App\Support\PrescriptionStatus::PENDING],
    'routes' => ['oral'=>'Oral','topical'=>'Topical','intravenous'=>'Intravenous','intramuscular'=>'Intramuscular','subcutaneous'=>'Subcutaneous','inhalation'=>'Inhalation','ophthalmic'=>'Ophthalmic','otic'=>'Otic','nasal'=>'Nasal','rectal'=>'Rectal','other'=>'Other'],
    'frequencies' => ['daily'=>'Once daily','bid'=>'Twice daily','tid'=>'Three times daily','qid'=>'Four times daily','q4h'=>'Every 4 hours','q6h'=>'Every 6 hours','q8h'=>'Every 8 hours','q12h'=>'Every 12 hours','prn'=>'As needed','bedtime'=>'At bedtime','custom'=>'Custom'],
];
