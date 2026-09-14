<?php
return [
    'clients'=>['model'=>App\Models\SalonClient::class,'module'=>'clients','create'=>'clients.create','update'=>'clients.update','archive'=>'clients.archive','event'=>'client','search'=>['first_name','middle_name','last_name','client_number','phone','email'],'relations'=>['preferredStylist','branch']],
    'stylists'=>['model'=>App\Models\SalonStaffProfile::class,'module'=>'stylists','create'=>'salon_staff.manage','update'=>'salon_staff.manage','event'=>'salon_staff','search'=>['display_name','staff_number','title'],'relations'=>['branches','services.branches']],
    'services'=>['model'=>App\Models\SalonService::class,'module'=>'services','create'=>'services.create','update'=>'services.update','archive'=>'services.archive','event'=>'service','search'=>['name','code'],'relations'=>['category','branches','stylists.branches']],
    'categories'=>['model'=>App\Models\ServiceCategory::class,'module'=>'services','create'=>'service_categories.manage','update'=>'service_categories.manage','event'=>'service_category','search'=>['name'],'relations'=>[]],
];
