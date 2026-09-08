<?php

return [
    'statuses' => ['scheduled' => ['Scheduled', 'blue'], 'confirmed' => ['Confirmed', 'cyan'], 'waiting' => ['Waiting', 'amber'], 'checked_in' => ['Checked In', 'purple'], 'in_consultation' => ['In Consultation', 'indigo'], 'completed' => ['Completed', 'green'], 'cancelled' => ['Cancelled', 'red'], 'no_show' => ['No Show', 'gray']],
    'terminal' => ['completed', 'cancelled', 'no_show'],
    'non_blocking' => ['cancelled', 'no_show'],
    'actions' => [
        'confirm' => ['to' => 'confirmed', 'from' => ['scheduled'], 'permission' => 'appointments.update', 'event' => 'confirmed'],
        'check-in' => ['to' => 'checked_in', 'from' => ['scheduled', 'confirmed', 'waiting'], 'permission' => 'appointments.check_in', 'timestamp' => 'checked_in_at', 'event' => 'checked_in'],
        'start-consultation' => ['to' => 'in_consultation', 'from' => ['checked_in'], 'permission' => 'appointments.start_consultation', 'timestamp' => 'consultation_started_at', 'event' => 'consultation_started'],
        'complete' => ['to' => 'completed', 'from' => ['in_consultation'], 'permission' => 'appointments.complete', 'timestamp' => 'completed_at', 'event' => 'completed'],
        'cancel' => ['to' => 'cancelled', 'from' => ['scheduled', 'confirmed', 'waiting', 'checked_in'], 'permission' => 'appointments.cancel', 'timestamp' => 'cancelled_at', 'event' => 'cancelled'],
        'no-show' => ['to' => 'no_show', 'from' => ['scheduled', 'confirmed'], 'permission' => 'appointments.update', 'event' => 'no_show'],
    ],
];
