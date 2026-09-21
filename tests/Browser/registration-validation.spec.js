import { test, expect } from '@playwright/test';
import { fixture } from './helpers/registration.js';

async function account(page) {
    await page.goto('/app/register');
    await page.locator('[name=owner_name]').fill('New Owner');
    await page.locator('[name=owner_email]').fill('owner@example.test');
    await page.locator('[name=owner_password]').fill('SecurePass12345');
    await page.locator('[name=owner_password_confirmation]').fill('SecurePass12345');
}
async function details(page) {
    await account(page);
    await page.getByRole('button', { name: 'Continue' }).click();
    await page.getByRole('radio').first().check();
    await page.getByRole('button', { name: 'Continue' }).click();
    await page.getByRole('radio').first().check();
    await page.getByRole('button', { name: 'Continue' }).click();
    await page.locator('[name=name]').fill('My Business');
}

for (const [field, target] of Object.entries({ owner_name: 0, owner_email: 0, owner_password: 0, owner_password_confirmation: 0, business_type_id: 1, plan_id: 2, name: 3, timezone: 3 })) {
    test(`final ${field} errors return to step ${target + 1} and preserve data`, async ({ page }) => {
        await fixture(page);
        await page.route('**/register', route => route.request().method() === 'POST' ? route.fulfill({ status: 422, json: { errors: { [field]: ['Please correct this field.'] } } }) : route.fallback());
        await details(page);
        await page.getByRole('button', { name: 'Start Free Trial' }).click();
        await expect(page.locator('.registration-steps li').nth(target)).toHaveAttribute('aria-current', 'step');
        await expect(page.locator(`#${field}-error`)).toHaveText('Please correct this field.');
        await expect(page.locator(`[name=${field}]`).first()).toHaveAttribute('aria-invalid', 'true');
        if (target === 0) await expect(page.locator('[name=owner_name]')).toHaveValue('New Owner');
        if (target === 3) await expect(page.locator('[name=name]')).toHaveValue('My Business');
    });
}

test('each invalid step stays in place with field errors and sends only its own fields', async ({ page }) => {
    await fixture(page);
    let invalid = true;
    const fields = [['owner_name', 'owner_email', 'owner_password', 'owner_password_confirmation'], ['business_type_id'], ['plan_id'], ['name', 'timezone']];
    await page.route('**/register/validate', route => {
        const data = route.request().postDataJSON();
        expect(Object.keys(data).sort()).toEqual(['step', ...fields[data.step - 1]].sort());
        return route.fulfill(invalid ? { status: 422, json: { errors: Object.fromEntries(fields[data.step - 1].map(field => [field, ['Invalid value.']])) } } : { json: { valid: true } });
    });
    await account(page);
    for (let index = 0; index < 4; index++) {
        invalid = true;
        await page.locator('.registration-next').click();
        await expect(page.locator(`#${fields[index][0]}-error`)).toHaveText('Invalid value.');
        await expect(page.locator('.registration-steps li').nth(index)).toHaveAttribute('aria-current', 'step');
        if (index < 3) {
            invalid = false;
            await page.locator('.registration-next').click();
            await expect(page.locator('.registration-steps li').nth(index + 1)).toHaveAttribute('aria-current', 'step');
        }
    }
});

test('final request disables submission and back while processing and submits once', async ({ page }) => {
    await fixture(page);
    let submissions = 0;
    let release;
    const pending = new Promise(resolve => { release = resolve; });
    await page.route('**/register', async route => {
        if (route.request().method() !== 'POST') return route.fallback();
        submissions++;
        await pending;
        await route.fallback();
    });
    await details(page);
    await page.getByRole('button', { name: 'Start Free Trial' }).click();
    await expect.poll(() => submissions).toBe(1);
    await expect(page.locator('.registration-next')).toBeDisabled();
    await expect(page.getByRole('button', { name: 'Back' })).toBeDisabled();
    await page.locator('form').evaluate(form => { form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true })); });
    release();
    await expect(page.getByRole('heading', { name: 'Your free trial has started' })).toBeVisible();
    expect(submissions).toBe(1);
});
