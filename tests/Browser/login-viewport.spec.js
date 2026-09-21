import { test, expect } from '@playwright/test';

test('branded login fits shorter desktop viewports and keeps the device inside its panel', async ({ page }) => {
    await page.route('**/api/v1/public/settings', route => route.fulfill({ json: { data: {
        'branding.login_logo': 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="100" height="100"/%3E',
    } } }));
    await page.goto('/app/login');
    await expect(page.getByRole('heading', { name: 'Welcome back' })).toBeVisible();
    const failures = [];
    for (const [width, height] of [[1920, 730], [1920, 720], [1536, 721], [2560, 1080], [1920, 800], [1920, 850], [1600, 800], [1440, 800], [1366, 680], [1280, 720], [1024, 600]]) {
        await page.setViewportSize({ width, height });
        const layout = await page.evaluate(() => {
            const panel = document.querySelector('.login-story').getBoundingClientRect();
            const device = document.querySelector('.login-product').getBoundingClientRect();
            const visible = getComputedStyle(document.querySelector('.login-product')).display !== 'none';
            return { overflow: document.documentElement.scrollHeight - innerHeight,
                horizontal: document.documentElement.scrollWidth - innerWidth,
                clippedDevice: visible && (device.top < panel.top || device.bottom > panel.bottom) };
        });
        if (layout.overflow > 2 || layout.horizontal > 0 || layout.clippedDevice) failures.push({ width, height, ...layout });
    }
    expect(failures).toEqual([]);
});
