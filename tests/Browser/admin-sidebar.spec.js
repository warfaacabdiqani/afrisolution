import { test, expect } from '@playwright/test';

test('platform sidebar groups navigation and fits the primary actions without scrolling', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await page.goto('/app/login');
    await page.getByLabel('Email address', { exact: true }).fill('browser-admin@example.test');
    await page.getByLabel('Password', { exact: true }).fill('BrowserTestPass123');
    await page.getByRole('button', { name: 'Sign in' }).click();
    await expect(page).toHaveURL(/app\/admin$/);

    const navigation = page.getByRole('navigation', { name: 'Platform administration' });
    await expect(navigation.getByText('Business', { exact: true })).toBeVisible();
    await expect(navigation.getByText('Billing', { exact: true })).toBeVisible();
    await expect(navigation.getByText('Access', { exact: true })).toBeVisible();
    await expect(navigation.getByText('Communication', { exact: true })).toBeVisible();
    await expect(navigation.getByText('System', { exact: true })).toBeVisible();
    await expect(navigation.getByRole('link', { name: 'Dashboard', exact: true })).toHaveAttribute('aria-current', 'page');
    await expect(page.getByRole('button', { name: 'Sign out' })).toBeInViewport();

    await page.getByRole('button', { name: 'Collapse sidebar' }).click();
    const sidebar = page.locator('.admin-sidebar');
    await expect(sidebar).toHaveCSS('width', '80px');
    expect(await sidebar.evaluate(element => element.scrollWidth <= element.clientWidth)).toBeTruthy();
    expect(await navigation.evaluate(element => element.scrollWidth <= element.clientWidth)).toBeTruthy();
    await expect(navigation.getByRole('link')).toHaveCount(11);
    await expect(navigation.getByText('Business', { exact: true })).toBeHidden();
    for (const label of ['Dashboard', 'Businesses / Tenants', 'Business Types', 'Subscription Plans', 'Subscriptions', 'Users', 'Roles & Permissions', 'Support Tickets', 'WhatsApp', 'Audit Log', 'System Settings']) {
        await navigation.getByRole('link', { name: label, exact: true }).hover();
        await expect(page.getByRole('tooltip')).toHaveText(label);
    }
    await expect(navigation.getByRole('link', { name: 'Dashboard', exact: true })).toHaveClass(/admin-nav-active/);
    await page.getByRole('button', { name: 'Sign out' }).hover();
    await expect(page.getByRole('tooltip')).toHaveText('Sign out');
    await page.getByRole('button', { name: 'Expand sidebar' }).click();
    await expect(navigation.getByText('Business', { exact: true })).toBeVisible();

    await page.setViewportSize({ width: 390, height: 844 });
    await page.getByRole('button', { name: 'Open menu' }).click();
    await expect(navigation).toBeVisible();
    await expect(navigation.getByRole('link', { name: 'Dashboard', exact: true })).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBeTruthy();
});
