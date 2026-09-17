const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
  testDir: './tests',
  timeout: 30000,
  use: {
    headless: true,
    // Base URL for E2E tests. Set via environment variable in CI or use default.
    // For local testing: export PLAYWRIGHT_BASE_URL=http://localhost:3000/marketplace
    // For production: export PLAYWRIGHT_BASE_URL=https://godemarsempire.com
    baseURL: process.env.PLAYWRIGHT_BASE_URL || 'https://godemarsempire.com'
  }
});
