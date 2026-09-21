import { test, expect } from '@playwright/test';

for (const width of [1440, 1024, 768, 390, 320]) {
    test(`public landing navigation and layout at ${width}px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        page.on('console', message => { if (message.type() === 'error' && !message.text().includes('401')) errors.push(message.text()); });
        await page.goto('/');
        await expect(page.getByRole('heading', { level: 1 })).toHaveText('Run your business from one connected workspace.');
        await page.getByRole('link', { name: 'Skip to content' }).focus();
        await page.keyboard.press('Enter');
        await expect(page.getByRole('heading', { level: 1 })).toBeFocused();
        await expect(page.locator('.business-card')).toHaveCount(4);
        await expect(page.locator('.feature-card')).toHaveCount(6);
        await expect(page.locator('.landing-steps li')).toHaveCount(4);
        await page.evaluate(() => window.scrollTo(0, 0));
        await page.screenshot({ path: test.info().outputPath(`landing-${width}.png`), fullPage: true });
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
        const columns = await page.locator('.feature-grid').evaluate(el => getComputedStyle(el).gridTemplateColumns.split(' ').length);
        expect(columns).toBe(width >= 1024 ? 3 : width >= 640 ? 2 : 1);
        for (const link of await page.locator('.landing a[href^="#"]').all()) {
            const href = await link.getAttribute('href');
            await expect(page.locator(href)).toHaveCount(1);
        }
        if (width < 768) {
            const toggle = page.getByRole('button', { name: /navigation$/ });
            await toggle.focus();
            await page.keyboard.press('Enter');
            await expect(toggle).toHaveAttribute('aria-expanded', 'true');
            await page.keyboard.press('Escape');
            await expect(toggle).toBeFocused();
            await expect(toggle).toHaveAttribute('aria-expanded', 'false');
            await toggle.click();
        }
        await page.getByRole('navigation', { name: 'Main navigation' }).getByRole('link', { name: 'Features', exact: true }).click();
        await expect(page).toHaveURL(/#features$/);
        await page.locator('.landing-hero').getByRole('link', { name: 'Start Free Trial' }).click();
        await expect(page).toHaveURL(/\/app\/register$/);
        await expect(page.getByRole('heading', { name: 'Create your account' })).toBeVisible();
        await page.goto('/');
        await page.locator('.landing-hero').getByRole('link', { name: 'Sign In', exact: true }).click();
        await expect(page).toHaveURL(/\/app\/login$/);
        await expect(page.getByRole('heading', { name: 'Welcome back' })).toBeVisible();
        expect(errors).toEqual([]);
    });
}
