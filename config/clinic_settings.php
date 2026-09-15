<?php
// One schema supplies defaults, server validation and field descriptions to the clinic UI.
$f = fn($label,$type,$default,$rules,$options=[],$help=null,$locked=false) => compact('label','type','default','rules','options','help','locked');
$text = fn($label,$default='',$max=255) => $f($label,'text',$default,"nullable|string|max:$max");
$bool = fn($label,$default=false,$locked=false,$help=null) => $f($label,'checkbox',$default,$locked ? 'required|boolean|'.($default?'accepted':'declined') : 'required|boolean',[],$help,$locked);
$number = fn($label,$default,$min,$max) => $f($label,'number',$default,"required|integer|between:$min,$max");
$prefix = fn($label,$default) => $f($label,'text',$default,'required|string|max:20|regex:/^[A-Za-z0-9-]+$/');
$currency = $f('Currency','select','USD','required|in:USD,KES,SOS,ETB,UGX,TZS,RWF,EUR,GBP,AED',['USD','KES','SOS','ETB','UGX','TZS','RWF','EUR','GBP','AED']);
return [
    'salon'=>['label'=>'Salon Preferences','feature'=>null,'fields'=>[
        'default_duration'=>$number('Default Appointment Duration (minutes)',30,5,480),
        'slot_interval'=>$number('Booking Interval (minutes)',15,5,60),
        'buffer_minutes'=>$number('Appointment Buffer (minutes)',0,0,120),
        'allow_overbooking'=>$bool('Allow Overbooking',false),
        'default_status'=>$f('Default Booking Status','select','scheduled','required|in:scheduled,confirmed',['scheduled','confirmed']),
        'cancellation_policy'=>$f('Cancellation Policy','textarea','','nullable|string|max:2000'),
        'deposit_policy'=>$f('Deposit Policy','textarea','','nullable|string|max:2000'),
        'allow_walk_in'=>$bool('Allow Walk-ins',true),
        'default_service_tax'=>$f('Default Service Tax (%)','number',0,'required|numeric|between:0,100'),
    ],'notice'=>'Booking preferences apply to salon appointments. Configure location business hours under Locations and stylist schedules on each stylist profile.'],
    'general'=>['label'=>'General','feature'=>null,'fields'=>[
        'name'=>$f('Clinic Name','text','','required|string|max:150'), 'code'=>$f('Clinic Code','text','','prohibited',[],'Stable clinic identifier; managed by the platform.',true),
        'email'=>$f('Email','email','','nullable|email|max:255'), 'phone'=>$text('Phone','',40),'alternative_phone'=>$text('Alternative Phone','',40),
        'website'=>$f('Website','url','','nullable|url:http,https|max:255'),'address'=>$text('Address','',1000),'city'=>$text('City','',100),'region'=>$text('Region / State','',100),'country'=>$text('Country','',100),
        'timezone'=>$f('Timezone','timezone','Africa/Nairobi','required|timezone'),'currency'=>$currency,'language'=>$f('Default Language','select','en','required|in:en',['en'],'English is the currently supported application language.'),
        'status'=>$f('Clinic Status','text','active','prohibited',[],'Operational status is managed by the platform to prevent accidental clinic lockout.',true),
    ]],
    'branding'=>['label'=>'Branding','feature'=>null,'fields'=>[]],
    'branches'=>['label'=>'Branches','feature'=>null,'fields'=>[]],
    'patients'=>['label'=>'Patient Settings','feature'=>'patient_management','fields'=>[
        'number_prefix'=>$prefix('Patient Number Prefix','PAT-'),'number_length'=>$number('Patient Number Length',6,3,12),'duplicate_warning'=>$bool('Warn about possible duplicate patients',true),
        'require_phone'=>$bool('Require Phone'),'require_date_of_birth'=>$bool('Require Date of Birth'),'require_gender'=>$bool('Require Gender',true,true,'Gender remains required by the existing patient record validation.'),
        'require_address'=>$bool('Require Address'),'require_emergency_contact'=>$bool('Require Emergency Contact'),
    ]],
    'appointments'=>['label'=>'Appointments','feature'=>'appointments','fields'=>[
        'default_duration'=>$number('Default Appointment Duration (minutes)',30,5,480),'slot_interval'=>$f('Time Slot Interval (minutes)','select',15,'required|integer|in:10,15,20,30',[10,15,20,30]),
        'working_start'=>$f('Default Working Start Time','time','08:00','required|date_format:H:i'),'working_end'=>$f('Default Working End Time','time','17:00','required|date_format:H:i|after:working_start'),
        'allow_walk_in'=>$bool('Allow Walk-In',true),'allow_double_booking'=>$bool('Allow Double Booking',false,true,'Overlapping bookings remain blocked by the scheduling service.'),
        'allow_overbooking'=>$bool('Allow Overbooking',false,true,'Clinician schedule, leave and overlap validation remains mandatory.'),
        'default_status'=>$f('Default Appointment Status','select','scheduled','required|in:scheduled',['scheduled']),
        'reminder_hours'=>$number('Appointment Reminder Timing (hours before)',24,1,168),'cancellation_policy'=>$f('Cancellation Policy','textarea','','nullable|string|max:2000'),
        'allow_same_day'=>$bool('Allow Same-Day Booking',true),
    ]],
    'clinical'=>['label'=>'Clinical','feature'=>['emr','prescriptions'],'notice'=>'Consultation preferences are stored for the future consultation workflow. Completed records remain protected. Prescription validity is a default for new prescriptions.', 'fields'=>[
        'require_vitals'=>$bool('Require Vital Signs Before Consultation'),'require_diagnosis'=>$bool('Require Diagnosis Before Completing Consultation',true),
        'allow_drafts'=>$bool('Allow Draft Consultations',true),'require_follow_up'=>$bool('Require Follow-up Date'),
        'edit_completed'=>$bool('Allow Editing Completed Consultations',false,true,'Completed clinical records cannot be reopened through settings.'),
        'prescription_validity_days'=>$number('Default Prescription Validity (days)',30,1,365),
        'notes_template'=>$f('Clinical Notes Template','textarea','','nullable|string|max:5000'),
    ]],
    'pharmacy'=>['label'=>'Pharmacy','feature'=>'pharmacy','notice'=>'Inventory preferences are ready for the Pharmacy module. This page does not perform stock movements.', 'fields'=>[
        'low_stock_threshold'=>$number('Low Stock Threshold',10,0,1000000),'expiry_warning_days'=>$number('Near Expiry Warning Days',30,0,365),
        'stock_method'=>$f('Default Stock Method','select','FEFO','required|in:FEFO',['FEFO']),
        'allow_negative_stock'=>$bool('Allow Negative Stock',false,true),'require_batch'=>$bool('Require Batch Number',true),'require_expiry'=>$bool('Require Expiry Date',true),
        'default_branch_id'=>$f('Default Pharmacy Branch','branch',null,'nullable|integer'),'deduct_on_dispensing'=>$bool('Deduct Stock on Dispensing',true,true,'Stock deduction will be handled transactionally by the Pharmacy inventory service.'),
    ]],
    'billing'=>['label'=>'Billing','feature'=>'billing','notice'=>'Numbering and payment preferences are stored for future billing documents. No payment gateway is connected here.', 'fields'=>[
        'invoice_prefix'=>$prefix('Invoice Prefix','INV-'),'receipt_prefix'=>$prefix('Receipt Prefix','RCT-'),'number_length'=>$number('Invoice Number Length',6,3,12),
        'tax_rate'=>$f('Default Tax Rate (%)','number',0,'required|numeric|between:0,100'),
        'discount_policy'=>$f('Default Discount Policy','select','approval_required','required|in:disabled,approval_required',['disabled','approval_required']),
        'consultation_fee'=>$f('Default Consultation Fee','number',0,'required|numeric|between:0,99999999'),'currency'=>$currency,
        'partial_payments'=>$bool('Allow Partial Payments',true),'refunds'=>$bool('Allow Refunds'),
        'payment_methods'=>$f('Enabled Payment Methods','multiselect',['cash'],'required|array|min:1',['cash','card','mobile_money','bank_transfer']),
    ]],
    'notifications'=>['label'=>'Notifications','feature'=>['email_notifications','sms_notifications','whatsapp_notifications'],'notice'=>'Provider status: Not configured. These are notification preferences; no email, SMS or WhatsApp delivery is simulated.', 'fields'=>[
        'email_enabled'=>$bool('Email Notifications'),'appointment_created'=>$bool('Appointment Created',true),'appointment_reminder'=>$bool('Appointment Reminder',true),'appointment_cancelled'=>$bool('Appointment Cancelled',true),
        'payment_receipt'=>$bool('Payment Receipt',true),'low_stock'=>$bool('Low Stock',true),'medicine_expiry'=>$bool('Medicine Expiry',true),'subscription_warning'=>$bool('Subscription Warning',true),
        'sms_enabled'=>$bool('SMS Notifications'),'whatsapp_enabled'=>$bool('WhatsApp Notifications'),
    ]],
    'documents'=>['label'=>'Documents','feature'=>null,'fields'=>[
        'prescription_header'=>$text('Prescription Header','Prescription'),'prescription_footer'=>$f('Prescription Footer','textarea','Please follow the medication instructions provided by your clinician.','nullable|string|max:2000'),
        'invoice_header'=>$text('Invoice Header','Invoice'),'invoice_footer'=>$f('Invoice Footer','textarea','','nullable|string|max:2000'),
        'receipt_footer'=>$f('Receipt Footer','textarea','','nullable|string|max:2000'),'medical_report_header'=>$text('Medical Report Header','Medical Report'),
        'show_address'=>$bool('Display Clinic Address',true),'show_phone'=>$bool('Display Phone',true),'show_email'=>$bool('Display Email',true),'show_license'=>$bool('Display Doctor License',true),
        'show_logo'=>$bool('Display Logo',true),'show_signature'=>$bool('Display Signature Area',true),
    ]],
    'security'=>['label'=>'Security','feature'=>null,'notice'=>'Clinic policies cannot weaken platform requirements. Two-factor authentication and first-login password reset are not implemented in the current authentication workflow.', 'fields'=>[
        'session_timeout'=>$number('Session Timeout / Inactivity Logout (minutes)',120,5,1440),
        'strong_passwords'=>$bool('Require Strong Passwords',true,true),'minimum_password_length'=>$number('Minimum Password Length',12,12,128),
        'allow_staff_login'=>$bool('Allow Staff Access',true,false,'Disabling access blocks clinic operations for staff without settings-management permission. Settings managers retain recovery access.'),
        'password_change_required'=>$bool('Require Password Change for New Staff',false,true,'Not supported by the current staff authentication workflow.'),
        'require_2fa'=>$bool('Require Two-Factor Authentication',false,true,'Not configured; enabling a preference would not provide actual 2FA enforcement.'),
    ]],
    'subscription'=>['label'=>'Subscription','feature'=>null,'fields'=>[]],
];
