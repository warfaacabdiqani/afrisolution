import { test, expect } from '@playwright/test';
import { fixture } from './helpers/registration.js';

test('registration keeps validation errors on the details step and sign-in remains available', async ({ page }) => {
    await fixture(page, true);
    await page.goto('/app/register');
    await page.getByPlaceholder('Enter your full name').fill('New Owner');
    await page.getByPlaceholder('you@yourcompany.com').fill('owner@example.test');
    await page.getByPlaceholder('Create a strong password').fill('SecurePass12345');
    await page.getByPlaceholder('Confirm your password').fill('SecurePass12345');
    await page.getByRole('button', { name: 'Continue' }).click();
    await page.getByText('Beauty Salon').click();
    await page.getByRole('button', { name: 'Continue' }).click();
    await page.getByText('Pro', { exact: true }).click();
    await page.getByRole('button', { name: 'Continue' }).click();
    await page.getByPlaceholder('Enter your business name').fill('Afriso');
    await page.getByRole('button', { name: 'Start Free Trial' }).click();
    await expect(page.getByRole('alert')).toContainText('Please enter a different business name.');
    await expect(page.getByRole('heading', { name: 'Business Information' })).toBeVisible();
    await page.locator('.registration-top a').click();
    await expect(page.getByRole('heading', { name: 'Welcome back' })).toBeVisible();
});

test('business type and plan cards support arrow-key selection and preserve selections when going back', async ({ page }) => {
    await fixture(page);
    await page.goto('/app/register');
    await page.getByPlaceholder('Enter your full name').fill('New Owner');
    await page.getByPlaceholder('you@yourcompany.com').fill('owner@example.test');
    await page.getByPlaceholder('Create a strong password').fill('SecurePass12345');
    await page.getByPlaceholder('Confirm your password').fill('SecurePass12345');
    await page.getByRole('button', { name: 'Continue' }).click();
    let radios = page.getByRole('radio');
    await radios.first().check();
    await radios.first().press('ArrowRight');
    await expect(radios.nth(1)).toBeChecked();
    await page.getByRole('button', { name: 'Continue' }).click();
    radios = page.getByRole('radio');
    await radios.first().check();
    await radios.first().press('ArrowRight');
    await expect(radios.nth(1)).toBeChecked();
    await page.getByRole('button', { name: 'Back' }).click();
    await expect(page.getByRole('radio').nth(1)).toBeChecked();
    await page.getByRole('button', { name: 'Continue' }).click();
    await expect(page.getByRole('radio').nth(1)).toBeChecked();
});

for (const [width, height] of [[1920, 900], [1600, 900], [1536, 730], [1366, 768]]) {
    test(`login fits the desktop viewport at ${width}×${height}`, async ({ page }) => {
        await page.setViewportSize({ width, height });
        await fixture(page);
        await page.goto('/app/login');
        await expect(page.getByRole('heading', { name: 'Welcome back' })).toBeVisible();
        const layout = await page.evaluate(() => ({ overflow: document.documentElement.scrollHeight - innerHeight,
            page: document.querySelector('.login-page').getBoundingClientRect().height,
            story: document.querySelector('.login-story').getBoundingClientRect().height,
            form: document.querySelector('.login-form-side').getBoundingClientRect().height }));
        expect(layout.overflow, JSON.stringify(layout)).toBeLessThanOrEqual(2);
    });
}

for (const [width, height] of [[1920, 1080], [1920, 900], [1920, 820], [1920, 720], [1600, 900], [1600, 800], [1536, 730], [1440, 700], [1366, 768], [1366, 650], [1280, 720], [1280, 640], [1280, 600], [1024, 768], [1024, 650], [820, 900], [390, 844]]) {
    test(`registration steps remain usable at ${width}×${height}`, async ({ page }) => {
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        page.on('console', message => { if (message.type() === 'error' && !message.text().includes('401')) errors.push(message.text()); });
        const capture = async name => {
            expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
            if ([1920, 1366, 390].includes(width)) await page.screenshot({ path: test.info().outputPath(name + '.png'), fullPage: true });
        };
        await page.setViewportSize({ width, height });
        await fixture(page);
        await page.goto('/app/register');
        await expect(page.getByRole('heading', { name: 'Create your account' })).toBeVisible();
        await capture('account');
        await expect(page.locator('.registration-story .login-brand')).toContainText('AFRI SOLUTION');
        await expect(page.locator('.registration-photo')).toHaveCount(0);
        await expect(page.locator('.registration-steps [aria-current="step"]')).toContainText('Account');
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
        await expect(page.locator('.registration-top a')).toHaveAttribute('href', '/app/login');
        await page.getByPlaceholder('Enter your full name').fill('New Owner');
        await page.getByPlaceholder('you@yourcompany.com').fill('owner@example.test');
        await page.getByPlaceholder('Create a strong password').fill('SecurePass12345');
        await page.getByPlaceholder('Confirm your password').fill('SecurePass12345');
        const assertDesktopFits = async () => {
            if (width >= 1024) {
                const layout = await page.evaluate(() => ({ overflow: document.documentElement.scrollHeight - innerHeight,
                    story: document.querySelector('.registration-story').getBoundingClientRect().height,
                    main: document.querySelector('.registration-main').getBoundingClientRect().height,
                    card: document.querySelector('.registration-card').getBoundingClientRect().height,
                    top: document.querySelector('.registration-top').getBoundingClientRect().height,
                    message: document.querySelector('.registration-message').getBoundingClientRect().height,
                    benefits: document.querySelector('.registration-benefits').getBoundingClientRect().height }));
                expect(layout.overflow, JSON.stringify(layout)).toBeLessThanOrEqual(2);
            }
        };
        await assertDesktopFits();
        await page.getByRole('button', { name: 'Continue' }).click();
        await expect(page.getByRole('heading', { name: 'Select Business Type' })).toBeVisible();
        await capture('business-type');
        await assertDesktopFits();
        await page.getByText('Beauty Salon').click();
        await page.getByRole('button', { name: 'Continue' }).click();
        await expect(page.getByRole('heading', { name: 'Select Subscription Plan' })).toBeVisible();
        await capture('plan');
        await assertDesktopFits();
        await page.getByText('Pro', { exact: true }).click();
        await page.getByRole('button', { name: 'Continue' }).click();
        await expect(page.getByRole('heading', { name: 'Business Information' })).toBeVisible();
        await capture('details');
        await assertDesktopFits();
        await page.getByRole('button', { name: 'Back' }).click();
        await expect(page.getByRole('heading', { name: 'Select Subscription Plan' })).toBeVisible();
        await page.getByRole('button', { name: 'Continue' }).click();
        await page.getByPlaceholder('Enter your business name').fill('Afriso');
        await page.getByRole('button', { name: 'Start Free Trial' }).click();
        await expect(page.getByRole('heading', { name: 'Your free trial has started' })).toBeVisible();
        await capture('success');
        await expect(page.locator('.registration-success')).toContainText('Beauty Salon');
        await assertDesktopFits();
        await expect(page.getByText('Email verification required')).toBeVisible();
        await expect(page.getByRole('button', { name: 'Verify Email' })).toBeVisible();
        await page.getByRole('button', { name: 'Verify Email' }).click();
        await expect(page).toHaveURL(/\/app\/verify-email$/);
        await expect(page.getByRole('heading', { name: 'Verify your email' })).toBeVisible();
        expect(errors).toEqual([]);
    });
}
