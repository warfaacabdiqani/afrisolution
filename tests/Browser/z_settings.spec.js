import {test,expect as baseExpect} from '@playwright/test';
const expect=baseExpect.configure({timeout:15000});

test('clinic settings save, persist, warn, upload and isolate sections',async({page})=>{
    test.setTimeout(180000);
    page.setDefaultTimeout(20000);
    const errors=[];page.on('pageerror',e=>errors.push(e.message));page.on('console',message=>{if(message.type()==='error')errors.push(message.text());});
    await page.setViewportSize({width:1536,height:1024});
    async function login(email,password){await page.goto('/app/login');await page.getByLabel('Email address',{exact:true}).fill(email);await page.getByLabel('Password',{exact:true}).fill(password);await page.getByRole('button',{name:'Sign in'}).click();await expect(page).not.toHaveURL(/\/login$/);}
    async function request(path,data,method='post'){
        const cookies=await page.context().cookies();const headers={Accept:'application/json',Origin:'http://127.0.0.1:8011','X-XSRF-TOKEN':decodeURIComponent(cookies.find(c=>c.name==='XSRF-TOKEN').value)};
        const response=await page.request[method](path,{headers,data});expect(response.ok(),await response.text()).toBeTruthy();return response.status()===204?null:response.json();
    }
    await login('browser-admin@example.test','BrowserTestPass123');
    const plan=await request('/api/v1/platform/plans',{name:'Settings browser plan',slug:'settings-browser',status:'active',price:0,currency:'USD',billing_period:'monthly',trial_days:14,branch_limit:3,member_limit:10,doctor_limit:5,appointment_limit:100,features:{billing:true,email_notifications:true,prescriptions:true,pharmacy:true,appointments:true,patient_management:true,clinicians:true,multi_branch:true}});
    await request('/api/v1/platform/tenants',{name:'Settings Test Clinic',slug:'settings-test',timezone:'Africa/Nairobi',plan_id:plan.data.id,owner_name:'Appointment Owner',owner_email:'settings-owner@example.test',owner_password:'AppointmentPass123',owner_password_confirmation:'AppointmentPass123'});
    await request('/logout',{});await login('settings-owner@example.test','AppointmentPass123');await expect(page).toHaveURL(/app\/dashboard/);
    const context=(await request('/api/v1/clinic/context',undefined,'get')).data;
    const today=context.today,branch=context.branch.id;const futureDate=new Date(`${today}T12:00:00Z`);futureDate.setUTCDate(futureDate.getUTCDate()+1);const future=futureDate.toISOString().slice(0,10);
    const specialty=await request('/api/v1/clinic/specialties',{name:'General Practitioner'});
    const doctor=await request('/api/v1/clinic/doctors',{first_name:'Ahmed',last_name:'Hassan',primary_branch_id:branch,specialty_ids:[specialty.data.id],availability_status:'available'});
    const patient=await request('/api/v1/clinic/patients',{first_name:'Amina',last_name:'Yusuf',gender:'female',phone:'+252 612 111 222'});


    // Anonymous session probes during fixture login return expected 401 responses.
    errors.length = 0;
    await page.goto('/app/settings');
    await expect(page).toHaveURL(/settings\/general$/);
    await expect(page.getByRole('heading',{name:'Clinic Settings',exact:true})).toBeVisible();
    await expect(page.getByRole('button',{name:'Save Changes',exact:true})).toBeDisabled();
    await page.getByLabel('Clinic Name',{exact:true}).fill('Warfaa Settings Clinic');
    await page.getByLabel('Phone',{exact:true}).fill('+252 611 444 555');
    await page.getByRole('button',{name:'Save Changes',exact:true}).click();
    await expect(page.getByRole('status').filter({hasText:'Clinic settings updated successfully.'})).toBeVisible();
    await page.reload();
    await expect(page.getByLabel('Clinic Name',{exact:true})).toHaveValue('Warfaa Settings Clinic');
    await page.getByLabel('Clinic Name',{exact:true}).fill('Unsaved name');
    await page.getByRole('navigation',{name:'Clinic settings categories'}).getByRole('link',{name:'Patient Settings',exact:true}).click();
    await expect(page.getByRole('dialog')).toBeVisible();
    await page.getByRole('button',{name:'Keep Editing',exact:true}).click();
    await expect(page).toHaveURL(/settings\/general$/);
    await page.getByRole('button',{name:'Discard Changes',exact:true}).click();
    await expect(page.getByLabel('Clinic Name',{exact:true})).toHaveValue('Warfaa Settings Clinic');
    await page.evaluate(()=>window.scrollTo(0,0));
    await page.screenshot({path:'storage/framework/testing/settings-desktop.png',fullPage:true,animations:'disabled'});
    async function tab(name){await page.getByRole('navigation',{name:'Clinic settings categories'}).getByRole('link',{name,exact:true}).click();}
    await tab('Patient Settings');
    await page.getByLabel('Patient Number Prefix',{exact:true}).fill('WAR-PAT-');
    await page.getByRole('button',{name:'Save Changes',exact:true}).click();
    await expect(page.getByRole('button',{name:'Save Changes',exact:true})).toBeDisabled();
    await page.reload();await expect(page.getByLabel('Patient Number Prefix',{exact:true})).toHaveValue('WAR-PAT-');
    await tab('Branding');
    await page.getByLabel('Choose Clinic Logo',{exact:true}).setInputFiles({name:'logo.png',mimeType:'image/png',buffer:Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aDXsAAAAASUVORK5CYII=','base64')});
    await page.getByRole('button',{name:'Save Changes',exact:true}).click();
    await expect(page.getByRole('button',{name:'Save Changes',exact:true})).toBeDisabled();
    await page.reload();await expect(page.getByRole('img',{name:'Clinic Logo',exact:true})).toBeVisible();
    await tab('Branches');await page.getByRole('button',{name:'Add Branch',exact:false}).click();
    await page.getByRole('dialog').getByLabel('Branch Name',{exact:true}).fill('East Clinic');
    await page.getByRole('dialog').getByRole('button',{name:'Save Changes',exact:true}).click();
    await expect(page.getByRole('cell',{name:'East Clinic',exact:true})).toBeVisible();
    await tab('Appointments');await page.getByLabel('Default Appointment Duration (minutes)',{exact:true}).fill('45');await page.getByRole('button',{name:'Save Changes',exact:true}).click();await expect(page.getByRole('button',{name:'Save Changes',exact:true})).toBeDisabled();
    await tab('Documents');await page.getByLabel('Prescription Footer',{exact:true}).fill('Warfaa patient care footer');await page.getByRole('button',{name:'Save Changes',exact:true}).click();
    await expect(page.frameLocator('iframe').getByText('Warfaa patient care footer',{exact:true})).toBeVisible();
    await tab('Subscription');await expect(page.getByRole('heading',{name:'Settings browser plan',exact:true})).toBeVisible();
    await page.setViewportSize({width:390,height:844});
    await page.getByLabel('Settings category',{exact:true}).selectOption('general');
    await expect(page.getByLabel('Clinic Name',{exact:true})).toHaveValue('Warfaa Settings Clinic');
    expect(await page.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth)).toBeTruthy();
    await page.screenshot({path:'storage/framework/testing/settings-mobile.png',fullPage:true,animations:'disabled'});
    expect(errors).toEqual([]);
});
