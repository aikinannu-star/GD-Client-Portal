# E2E Tests - GD Client Portal

Browser automation tests for the GD Client Portal marketplace functionality using Playwright.

## Status in CI/CD

**Currently disabled in GitHub Actions CI** (`e2e.yml` is set to `workflow_dispatch` only with explicit `if: false`).

### Why E2E is not part of the CI release gate

1. **Environment Requirements**: E2E tests require a fully provisioned WordPress environment with:
   - Active GD Client Portal plugin
   - WooCommerce integration
   - Marketplace data/products loaded
   - Proper authentication state (if needed)

2. **Network Access**: Tests point to external URLs which GitHub Actions CI cannot reliably access

3. **Separation of Concerns**: 
   - **Release Certification** is handled by `release-certification.yml` → PHPUnit against disposable WordPress + MySQL
   - **E2E Browser Tests** validate client-side rendering and interactions (separate workflow)

## Running E2E Tests Locally

### Prerequisites
- Node.js 18+
- Playwright browsers installed

### Setup

```bash
cd e2e
npm install
npx playwright install --with-deps
```

### Run Tests

Against production marketplace:
```bash
npm test
```

Against local WordPress instance:
```bash
export PLAYWRIGHT_BASE_URL=http://localhost:3000
npm test
```

### Configuration

Edit `playwright.config.js` to adjust:
- `timeout`: Selector/navigation timeout (default: 30s)
- `baseURL`: Via environment variable `PLAYWRIGHT_BASE_URL` (default: `https://godemarsempire.com`)

## Test Files

### `add-to-cart.spec.js`
- Validates marketplace page rendering
- Verifies product images load correctly
- Tests add-to-cart functionality and cart redirect

## Future: CI Re-enablement

To integrate E2E into CI:

1. Provision a local WordPress + WooCommerce environment in the workflow
2. Activate the GD Client Portal plugin
3. Populate test fixture marketplace data
4. Update workflow to set `PLAYWRIGHT_BASE_URL=http://localhost/marketplace`
5. Ensure test isolation (cleanup after each run)

This would require a separate job (or extend `php-tests.yml`) to set up a persistent test environment for the duration of the E2E test run.

## Debugging

Run tests in debug mode:
```bash
npx playwright test --debug
```

Generate trace for failure investigation:
```bash
npx playwright test --trace on
```

View traces:
```bash
npx playwright show-trace trace.zip
```
