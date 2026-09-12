import {test,expect as baseExpect} from '@playwright/test';
const expect=baseExpect.configure({timeout:15000});

test('prescriptions create, edit, print, duplicate, cancel, search and responsive profile history',async({page})=>{
    test.setTimeout(180000);
    page.setDefaultTimeout(20000);
    const errors=[];page.on('pageerror',e=>errors.push(e.message));
    await page.setViewportSize({width:1536,height:1024});
    async function login(email,password){await page.goto('/app/login');await page.getByLabel('Email address',{exact:true}).fill(email);await page.getByLabel('Password',{exact:true}).fill(password);await page.getByRole('button',{name:'Sign in'}).click();await expect(page).not.toHaveURL(/\/login$/);}
    async function request(path,data,method='post'){
        const cookies=await page.context().cookies();const headers={Accept:'application/json',Origin:'http://127.0.0.1:8011','X-XSRF-TOKEN':decodeURIComponent(cookies.find(c=>c.name==='XSRF-TOKEN').value)};
        const response=await page.request[method](path,{headers,data});expect(response.ok(),await response.text()).toBeTruthy();return response.status()===204?null:response.json();
    }
    await login('browser-admin@example.test','BrowserTestPass123');
    const plan=await request('/api/v1/platform/plans',{name:'Prescription browser plan',slug:'prescription-browser',status:'active',price:0,currency:'USD',billing_period:'monthly',trial_days:14,branch_limit:3,member_limit:10,doctor_limit:5,appointment_limit:100,features:{prescriptions:true,pharmacy:true,appointments:true,patient_management:true,clinicians:true,multi_branch:true}});
    await request('/api/v1/platform/tenants',{name:'Prescription Test Clinic',slug:'prescription-test',timezone:'Africa/Nairobi',plan_id:plan.data.id,owner_name:'Appointment Owner',owner_email:'prescription-owner@example.test',owner_password:'AppointmentPass123',owner_password_confirmation:'AppointmentPass123'});
    await request('/logout',{});await login('prescription-owner@example.test','AppointmentPass123');await expect(page).toHaveURL(/app\/dashboard/);
    const context=(await request('/api/v1/clinic/context',undefined,'get')).data;
    const today=context.today,branch=context.branch.id;const futureDate=new Date(`${today}T12:00:00Z`);futureDate.setUTCDate(futureDate.getUTCDate()+1);const future=futureDate.toISOString().slice(0,10);
    const specialty=await request('/api/v1/clinic/specialties',{name:'General Practitioner'});
    const doctor=await request('/api/v1/clinic/doctors',{first_name:'Ahmed',last_name:'Hassan',primary_branch_id:branch,specialty_ids:[specialty.data.id],availability_status:'available'});
    const patient=await request('/api/v1/clinic/patients',{first_name:'Amina',last_name:'Yusuf',gender:'female',phone:'+252 612 111 222'});

    await page.goto('/app/prescriptions');
    await expect(page.getByText('No prescriptions have been created yet.')).toBeVisible();
    await page.getByRole('link',{name:'New Prescription',exact:false}).click();
    await expect(page).toHaveURL(/prescriptions\/create$/);
    await page.getByLabel('Patient *',{exact:true}).fill('Amina');
    await page.locator('.rx-search-results').getByRole('button').filter({hasText:'Amina Yusuf'}).click();
    await page.getByLabel('Prescriber *',{exact:true}).selectOption(String(doctor.data.id));
    async function medication(index,name){const row=page.locator('.rx-medication').nth(index);await row.getByLabel('Medication *',{exact:true}).fill(name);await row.getByLabel('Dose *',{exact:true}).fill('1 tablet');await row.getByLabel('Route *',{exact:true}).selectOption('oral');await row.getByLabel('Frequency *',{exact:true}).selectOption('bid');await row.getByLabel('Duration *',{exact:true}).fill('5 days');await row.getByLabel('Quantity',{exact:true}).fill('10');}
    await medication(0,'Browser Medicine');
    await page.getByRole('button',{name:'Add Medication',exact:false}).click();
    await medication(1,'Second Medicine');
    await page.getByLabel('Internal Notes').fill('Private browser note');
    await page.getByRole('button',{name:'Save Draft',exact:true}).click();
    await expect(page.getByRole('heading',{name:'RX-000001',exact:true})).toBeVisible();
    await expect(page.locator('.patient-page-header .rx-status')).toHaveText('Draft');
    const detail=page.url();
    await page.getByRole('link',{name:'Edit',exact:true}).first().click();
    await page.getByRole('button',{name:'Save Prescription',exact:true}).click();
    await expect(page.locator('.patient-page-header .rx-status')).toHaveText('Active');
    await page.getByRole('link',{name:'Print',exact:true}).click();
    await expect(page.frameLocator('iframe').getByText('Browser Medicine',{exact:true})).toBeVisible();
    await expect(page.frameLocator('iframe').getByText('Private browser note')).toHaveCount(0);
    await page.goto('/app/prescriptions');
    await expect(page.locator('.rx-kpis strong').first()).toHaveText('1');
    await page.getByLabel('Search prescriptions',{exact:true}).fill('No matching medicine');
    await expect(page.getByText('No prescriptions match your search.')).toBeVisible();
    await page.getByRole('button',{name:'Reset',exact:true}).click();
    await expect(page.locator('.rx-desktop').getByText('RX-000001',{exact:true})).toBeVisible();
    await page.screenshot({path:'storage/framework/testing/prescriptions-desktop.png',fullPage:true,animations:'disabled'});
    await page.setViewportSize({width:390,height:844});
    await expect(page.locator('.rx-mobile-card')).toBeVisible();
    expect(await page.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth)).toBeTruthy();
    await page.getByRole('button',{name:'Filters',exact:true}).click();
    await page.getByRole('dialog',{name:'Prescription filters'}).getByLabel('Status',{exact:true}).selectOption('active');
    await page.getByRole('button',{name:'Show results',exact:true}).click();
    await expect(page.locator('.rx-mobile-card')).toBeVisible();
    await page.screenshot({path:'storage/framework/testing/prescriptions-mobile.png',fullPage:true});
    await page.setViewportSize({width:1536,height:1024});
    await page.goto(detail);
    await page.getByLabel('Prescription actions').click();
    await page.getByRole('link',{name:'Duplicate',exact:true}).click();
    await expect(page.getByLabel('Medication *',{exact:true}).first()).toHaveValue('Browser Medicine');
    await page.getByRole('button',{name:'Save Draft',exact:true}).click();
    await expect(page.getByRole('heading',{name:'RX-000002',exact:true})).toBeVisible();
    await page.getByLabel('Prescription actions').click();
    await page.getByRole('button',{name:'Cancel Prescription',exact:true}).click();
    await page.getByLabel('Cancellation Reason *').fill('Created in error');
    await page.getByRole('dialog').getByRole('button',{name:'Cancel Prescription',exact:true}).click();
    await expect(page.locator('.patient-page-header .rx-status')).toHaveText('Cancelled');
    await page.goto('/app/patients/'+patient.data.id+'/prescriptions');
    await expect(page.locator('.rx-desktop').getByText('RX-000001',{exact:true})).toBeVisible();
    await expect(page.locator('.rx-desktop').getByText('RX-000002',{exact:true})).toBeVisible();
    await page.goto('/app/doctors/'+doctor.data.id+'/prescriptions');
    await expect(page.locator('.rx-desktop').getByText('RX-000001',{exact:true})).toBeVisible();
    await page.goto(detail);
    await page.getByLabel('Prescription actions').click();
    await page.getByRole('button',{name:'Send to Pharmacy',exact:true}).click();
    await expect(page.locator('.patient-page-header .rx-status')).toHaveText('Pending');
    expect(errors).toEqual([]);
});


