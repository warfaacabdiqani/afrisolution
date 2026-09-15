import { test, expect } from '@playwright/test';
import { spawnSync } from 'node:child_process';
import { env, php } from '../../playwright.config.js';
test.beforeAll(()=>{const r=spawnSync(php,['tests/Browser/salon-booking-fixtures.php'],{env,encoding:'utf8'});if(r.status!==0)throw new Error(r.stderr||r.stdout);});
test('salon calendar, multi-service booking, workflow, invoice, payment and profiles',async({page})=>{
    test.setTimeout(180000);page.setDefaultTimeout(15000);
    const errors=[];page.on('pageerror',e=>errors.push(e.message));page.on('console',m=>{if(m.type()==='warning'&&m.text().includes('[Vue warn]'))errors.push(m.text());});
    await page.goto('/app/login');await page.getByLabel('Email address',{exact:true}).fill('booking-owner@example.test');await page.getByLabel('Password',{exact:true}).fill('BrowserTestPass123');await page.getByRole('button',{name:'Sign in',exact:true}).click();await expect(page).toHaveURL(/app\/dashboard$/);
    await expect(page.locator('[data-widget=total_clients]')).toContainText('1');await expect(page.locator('[data-widget=active_stylists]')).toContainText('1');
    await page.getByRole('navigation',{name:'Business navigation'}).getByRole('link',{name:'Appointments',exact:true}).click();
    await expect(page.getByRole('button',{name:'Week',exact:true})).toHaveAttribute('aria-pressed','true');
    for(const name of ['Month','Day','Schedule','Stylist View']){await page.getByRole('button',{name,exact:true}).click();await expect(page.getByRole('button',{name,exact:true})).toHaveAttribute('aria-pressed','true');}
    await page.getByRole('button',{name:'+ New Appointment',exact:true}).click();
    await page.getByRole('combobox',{name:'Client',exact:true}).selectOption({label:'Amina Ali · CLI-000001 · 0700123456'});
    await page.getByRole('checkbox',{name:'Haircut',exact:true}).check();await page.getByRole('checkbox',{name:'Color',exact:true}).check();
    await expect(page.getByText('90 minutes',{exact:false})).toBeVisible();await expect(page.getByText('Total: USD 45.00',{exact:true})).toBeVisible();
    const today=await page.getByLabel('Date',{exact:true}).inputValue();const tomorrow=new Date(today+'T12:00:00Z');tomorrow.setUTCDate(tomorrow.getUTCDate()+1);
    await page.getByLabel('Date',{exact:true}).fill(tomorrow.toISOString().slice(0,10));await page.getByLabel('Start Time',{exact:true}).fill('10:00');
    await page.getByRole('combobox',{name:'Stylist',exact:true}).selectOption({label:'Asha Stylist'});
    await page.getByRole('button',{name:'Save Appointment',exact:true}).click();await expect(page.getByRole('dialog')).toContainText('APT-000001');
    await page.getByRole('link',{name:'Reschedule Appointment',exact:true}).click();await page.getByLabel('Start Time',{exact:true}).fill('14:00');await page.getByRole('button',{name:'Reschedule Appointment',exact:true}).click();await expect(page.getByRole('dialog')).toContainText('14:00');
    await page.getByLabel('Cancellation Reason',{exact:true}).fill('Client requested cancellation');await page.getByRole('button',{name:'Cancel Appointment',exact:true}).click();await expect(page.getByRole('dialog')).toContainText('Cancelled');await page.getByRole('button',{name:'Close',exact:true}).click();
    await page.getByRole('button',{name:'+ Walk-In Appointment',exact:true}).click();await page.getByRole('combobox',{name:'Client',exact:true}).selectOption({label:'Amina Ali · CLI-000001 · 0700123456'});await page.getByRole('checkbox',{name:'Haircut',exact:true}).check();
    // Walk-ins can be recorded earlier today; this keeps the test independent of host time.
    await page.getByLabel('Start Time',{exact:true}).fill('08:00');await page.getByRole('combobox',{name:'Stylist',exact:true}).selectOption({label:'Asha Stylist'});await page.getByRole('button',{name:'Save Appointment',exact:true}).click();
    await expect(page.getByRole('dialog')).toContainText('Waiting');
    for(const name of ['Check In','Start Service','Complete'])await page.getByRole('dialog').getByRole('button',{name,exact:true}).click();
    await expect(page.getByRole('dialog')).toContainText('Completed');await page.getByRole('button',{name:'Create Invoice',exact:true}).click();await expect(page).toHaveURL(/billing\/invoices\/\d+$/);
    await expect(page.getByText('Total: USD 15.00',{exact:true})).toBeVisible();await page.getByRole('button',{name:'Record Payment',exact:true}).click();await expect(page.getByText('Balance: 0.00',{exact:false})).toBeVisible();
    await page.goto('/app/clients');await page.getByRole('row').filter({hasText:'Amina Ali'}).getByRole('link',{name:'View',exact:true}).click();await expect(page.getByRole('heading',{name:'Appointment History',exact:true})).toBeVisible();await expect(page.getByText('Last Visit:',{exact:false})).not.toContainText('No completed visit');
    await page.goto('/app/stylists');await page.getByRole('row').filter({hasText:'Asha Stylist'}).getByRole('link',{name:'View',exact:true}).click();await expect(page.getByRole('heading',{name:'Stylist Schedule',exact:true})).toBeVisible();await page.getByRole('button',{name:'Save Working Hours',exact:true}).click();await expect(page.getByRole('status')).toHaveText('Working hours saved.');
    await page.goto('/app/dashboard');await expect(page.locator('[data-widget=monthly_revenue]')).toContainText('15');
    await page.setViewportSize({width:390,height:844});await page.goto('/app/appointments');await expect(page.getByRole('button',{name:'Day',exact:true})).toHaveAttribute('aria-pressed','true');
    expect(await page.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth)).toBeTruthy();
    expect(errors).toEqual([]);
});
