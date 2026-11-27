/**
 * Browser tests against pages rendered from the components, see tests/Functional/BrowserPagesTest.php
 *
 * The web server first renders the pages to var/browser with PHPUnit and then serves the repository with the built-in
 * server of PHP. Every test runs in Chromium and Firefox, once with JavaScript and once without.
 */
import { defineConfig, devices } from '@playwright/test';

const browsers = { chromium: devices['Desktop Chrome'], firefox: devices['Desktop Firefox'] };
const projects = [];
for (const [name, device] of Object.entries(browsers)) {
    projects.push({ name, use: { ...device } });
    projects.push({ name: `${name}-no-js`, use: { ...device, javaScriptEnabled: false } });
}

const port = 8791;

export default defineConfig({
    testDir: 'tests/Browser',
    testMatch: '**/*.browser.js',
    outputDir: 'var/playwright',
    forbidOnly: true,
    fullyParallel: true,
    reporter: 'list',
    use: { baseURL: `http://127.0.0.1:${port}` },
    webServer: {
        command: `vendor/bin/phpunit tests/Functional/BrowserPagesTest.php && php -S 127.0.0.1:${port} -t .`,
        url: `http://127.0.0.1:${port}/var/browser/blocked.html`,
        reuseExistingServer: false,
        stderr: 'ignore',
        timeout: 120000,
    },
    projects,
});
