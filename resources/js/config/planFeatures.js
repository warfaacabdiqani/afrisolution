export const planFeatureGroups = [
    ['Core clinical', [['patient_management','Patient Management'],['appointments','Appointments'],['clinicians','Doctors / Clinicians'],['emr','Electronic Medical Records'],['vital_signs','Vital Signs'],['prescriptions','Prescriptions']]],
    ['Pharmacy', [['pharmacy','Pharmacy'],['medicine_inventory','Medicine Inventory'],['suppliers','Suppliers'],['purchases','Purchases'],['stock_alerts','Stock Alerts']]],
    ['Finance', [['billing','Billing'],['invoices','Invoices'],['payments','Payments'],['receipts','Receipts'],['advanced_financial_reports','Advanced Financial Reports']]],
    ['Reporting', [['basic_reports','Basic Reports'],['advanced_reports','Advanced Reports'],['data_export','Data Export']]],
    ['Communication', [['email_notifications','Email Notifications'],['sms_notifications','SMS Notifications'],['whatsapp_notifications','WhatsApp Notifications']]],
    ['Advanced', [['api_access','API Access'],['custom_domain','Custom Domain'],['advanced_audit_logs','Advanced Audit Logs'],['multi_branch','Multi-Branch'],['priority_support','Priority Support']]],
];

export const defaultFeatures = () => Object.fromEntries(planFeatureGroups.flatMap(([, features]) => features.map(([key]) => [key, false])));
