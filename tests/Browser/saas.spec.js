import { test, expect } from '@playwright/test';

test('clinic administration uses dedicated routes and preserves tenant access', async ({ page }) => {
    const errors=[];
    page.on('pageerror', error => errors.push(error.message));
    await page.goto('/app/admin/clinics');
    await expect(page).toHaveURL(/app\/login/);
    await page.getByLabel('Email',{exact:true}).fill('browser-admin@example.test');
    await page.getByLabel('Password',{exact:true}).fill('BrowserTestPass123');
    await page.getByRole('button',{name:'Sign in'}).click();
    await expect(page.getByRole('heading',{name:/Welcome back/})).toBeVisible();

    await page.getByRole('link',{name:/Subscription Plans/}).click();
    await expect(page.getByRole('heading',{name:'Create Subscription Plan'})).toHaveCount(0);
    await page.getByRole('link',{name:/Add New Plan/}).click();
    await expect(page).toHaveURL(/admin\/plans\/create$/);
    await page.getByLabel('Plan Name').fill('Starter');
    await page.getByLabel('Plan Code / Slug').fill('starter');
    await page.getByLabel('Patient Management').check();
    await page.getByRole('button',{name:'Create Plan'}).click();
    await expect(page).toHaveURL(/admin\/plans\/1\?created=1$/);
    await expect(page.getByRole('status')).toContainText('Subscription plan created successfully.');
    await expect(page.getByRole('button',{name:'Delete Plan'})).toHaveCount(0);
    await page.getByRole('button',{name:/More/}).click();
    await expect(page.getByRole('button',{name:'Delete Plan'})).toBeVisible();
    await page.getByRole('link',{name:'Features',exact:true}).click();
    await expect(page.getByText('Enabled · Patient Management')).toBeVisible();
    await page.goBack();
    await expect(page).toHaveURL(/admin\/plans\/1\?created=1$/);
    await page.reload();
    await expect(page.getByRole('heading',{name:'Starter'})).toBeVisible();

    await page.getByRole('link',{name:/Clinics \/ Tenants/}).click();
    await expect(page).toHaveURL(/admin\/clinics$/);
    await expect(page.getByRole('heading',{name:'Create Clinic'})).toHaveCount(0);
    await page.getByRole('link',{name:/Add New Clinic/}).first().click();
    await expect(page).toHaveURL(/admin\/clinics\/create$/);
    await page.getByLabel('Clinic name').fill('Browser Clinic');
    await page.getByLabel('Unique clinic code').fill('browser-clinic');
    await page.getByLabel('Plan').selectOption('1');
    await page.getByLabel('Owner name').fill('Clinic Owner');
    await page.getByLabel('Owner email').fill('browser-owner@example.test');
    await page.locator('input[type="password"]').first().fill('OwnerTestPass123');
    await page.getByLabel('Confirm password').fill('OwnerTestPass123');
    await page.getByRole('button',{name:'Create Clinic'}).click();

    await expect(page).toHaveURL(/admin\/clinics\/1\?created=1$/);
    await expect(page.getByText('Clinic created successfully.')).toBeVisible();
    await page.reload();
    await expect(page.getByRole('heading',{name:'Browser Clinic'})).toBeVisible();
    await page.getByRole('link',{name:'Branches',exact:true}).click();
    await expect(page).toHaveURL(/admin\/clinics\/1\/branches$/);
    await page.getByRole('button',{name:/Add Branch/}).click();
    await page.getByRole('dialog').getByLabel('Branch name').fill('Over limit');
    await page.getByRole('dialog').getByRole('button',{name:'Add Branch'}).click();
    await expect(page.getByRole('alert')).toContainText('branch limit');
    await page.getByRole('dialog').getByRole('button',{name:'Cancel'}).click();

    await page.getByRole('link',{name:'Members',exact:true}).click();
    await expect(page).toHaveURL(/admin\/clinics\/1\/members$/);
    await page.goBack();
    await expect(page).toHaveURL(/admin\/clinics\/1\/branches$/);
    await page.goForward();
    await expect(page).toHaveURL(/admin\/clinics\/1\/members$/);
    await page.screenshot({path:'test-results/clinic-management.png',fullPage:true});
    await page.setViewportSize({width:390,height:844});
    expect(await page.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth)).toBeTruthy();
    await page.screenshot({path:'test-results/clinic-management-mobile.png',fullPage:true});

    await page.getByRole('button',{name:'Open menu'}).click();
    await page.getByRole('button',{name:'Sign out'}).click();
    await page.getByLabel('Email',{exact:true}).fill('browser-owner@example.test');
    await page.getByLabel('Password',{exact:true}).fill('OwnerTestPass123');
    await page.getByRole('button',{name:'Sign in'}).click();
    await expect(page.getByText('Main branch',{exact:true})).toBeVisible();
    await page.goto('/app/admin/clinics/1');
    await expect(page).toHaveURL(/app\/clinics/);
    expect(errors).toEqual([]);
});
