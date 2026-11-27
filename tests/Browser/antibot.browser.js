/**
 * Nobody reaches the protective fields, they travel with the form, and the notice is accessible
 */
import { expect, test } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

/**
 * Tags of the rules of axe for WCAG 2.2 AA
 *
 * @type {string[]}
 */
const wcag = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

/**
 * Check a page with axe against WCAG 2.2 AA
 *
 * axe is a script itself. For a page without JavaScript it checks a copy of what the browser rendered, under the same
 * address and with the same styles, but without the scripts of the page.
 *
 * @param {import('@playwright/test').Page} page Page
 * @param {import('@playwright/test').Browser} browser Browser
 * @param {boolean} javaScriptEnabled Whether the page runs JavaScript
 */
async function accessible(page, browser, javaScriptEnabled) {
    if (javaScriptEnabled) {
        expect((await new AxeBuilder({ page }).withTags(wcag).analyze()).violations).toEqual([]);

        return;
    }

    const url = page.url();
    const html = (await page.content()).replace(/<script\b[^>]*>[\s\S]*?<\/script>/giu, '');
    const context = await browser.newContext({ javaScriptEnabled: true, viewport: page.viewportSize() });
    try {
        const copy = await context.newPage();
        await copy.route(url, (route) => route.fulfill({ contentType: 'text/html; charset=utf-8', body: html }));
        await copy.goto(url);
        expect((await new AxeBuilder({ page: copy }).withTags(wcag).analyze()).violations).toEqual([]);
    } finally {
        await context.close();
    }
}

test('the traps are invisible, out of the order of focus and hidden from assistive technology', async ({
    page,
    browser,
    javaScriptEnabled,
}) => {
    await page.goto('/var/browser/form.html');
    const traps = page.locator('.AntibotFields__trap');
    await expect(traps).toHaveCount(2);
    for (const trap of await traps.all()) {
        await expect(trap).toBeHidden();
    }

    await expect(page.getByRole('textbox')).toHaveCount(1);
    await page.getByLabel('Name').focus();
    await page.keyboard.press('Tab');
    await expect(page.getByRole('button', { name: 'Send' })).toBeFocused();
    await accessible(page, browser, javaScriptEnabled);
});

test('the protective fields travel with the form, and the traps stay empty', async ({ page }) => {
    await page.goto('/var/browser/form.html');
    await page.getByLabel('Name').fill('Ada');
    await page.getByRole('button', { name: 'Send' }).click();
    await expect(page).toHaveTitle('Sent');

    const prefix = 'tx_twaccounts_registration[registration_3f2c9a41d0b7e85c6a19f4e2b7d03c58a9e1f6b2]';
    const parameters = new URL(page.url()).searchParams;
    expect(parameters.get('name')).toBe('Ada');
    expect(parameters.get(`${prefix}[hmac]`)).toBe('9c1f2e7a4b6d8035e1a2c3f4b5d6e7f8091a2b3c');
    expect(parameters.get(`${prefix}[ts]`)).toBe('1795000000');
    expect(parameters.get(`${prefix}[email]`)).toBe('');
    expect(parameters.get(`${prefix}[homepage]`)).toBe('');
});

test('the notice is announced, names the address and is accessible', async ({ page, browser, javaScriptEnabled }) => {
    await page.goto('/var/browser/blocked.html');
    const notice = page.getByRole('alert');
    await expect(notice).toBeVisible();
    await expect(notice).toContainText('This form is currently unavailable to you.');
    await expect(notice.getByRole('link', { name: 'hello@example.org' })).toHaveAttribute(
        'href',
        'mailto:hello@example.org',
    );
    await expect(page.locator('form')).toHaveCount(0);
    await accessible(page, browser, javaScriptEnabled);
});
