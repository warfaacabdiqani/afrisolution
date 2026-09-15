<?php
return [
    'statuses' => ['scheduled' => ['Scheduled','blue'], 'confirmed' => ['Confirmed','violet'], 'waiting' => ['Waiting','amber'], 'checked_in' => ['Checked In','cyan'], 'in_service' => ['In Service','violet'], 'completed' => ['Completed','green'], 'cancelled' => ['Cancelled','gray'], 'no_show' => ['No Show','red']],
    'non_blocking' => ['cancelled','no_show'],
    'actions' => [
        'confirm' => ['from'=>['scheduled'],'to'=>'confirmed','permission'=>'appointments.update'],
        'check-in' => ['from'=>['scheduled','confirmed','waiting'],'to'=>'checked_in','permission'=>'appointments.check_in','timestamp'=>'checked_in_at'],
        'start-service' => ['from'=>['checked_in'],'to'=>'in_service','permission'=>'appointments.start_service','timestamp'=>'service_started_at'],
        'complete' => ['from'=>['in_service'],'to'=>'completed','permission'=>'appointments.complete','timestamp'=>'completed_at'],
        'cancel' => ['from'=>['scheduled','confirmed','waiting','checked_in'],'to'=>'cancelled','permission'=>'appointments.cancel','timestamp'=>'cancelled_at'],
        'no-show' => ['from'=>['scheduled','confirmed','waiting'],'to'=>'no_show','permission'=>'appointments.cancel'],
    ],
];
