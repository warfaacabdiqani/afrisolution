<?php

return [
    'numbering' => 'Universal',
    'teeth' => [...array_map('strval', range(1, 32)), ...range('A', 'T')],
    'surfaces' => ['M' => 'Mesial', 'D' => 'Distal', 'O' => 'Occlusal', 'I' => 'Incisal', 'B' => 'Buccal / facial', 'L' => 'Lingual / palatal'],
    'conditions' => ['sound' => 'Sound', 'caries' => 'Caries', 'restoration' => 'Restoration', 'crown' => 'Crown',
        'missing' => 'Missing', 'unerupted' => 'Unerupted', 'fracture' => 'Fracture', 'implant' => 'Implant',
        'root_canal' => 'Root canal treated', 'other' => 'Other finding'],
];
