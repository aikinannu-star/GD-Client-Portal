const { test, expect } = require('@playwright/test');

test('marketplace images and add-to-cart success redirects to cart', async ({ page }) => {
  // Stub admin-ajax add-to-cart to return a success with cart redirect
  await page.route('**/admin-ajax.php', route => {
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ success: true, data: { redirect: '/cart/' } })
    });
  });

  await page.goto('/marketplace/', { waitUntil: 'domcontentloaded' });

  // Wait for marketplace items to render
  await page.waitForSelector('.gd-market-item', { timeout: 10000 });

  // Ensure images exist and are loaded
  const imgs = await page.$$('.gd-market-image img');
  expect(imgs.length).toBeGreaterThan(0);
  for (const img of imgs) {
    await expect(img).toBeVisible();
    const naturalWidth = await img.evaluate((n) => n.naturalWidth);
    expect(naturalWidth).toBeGreaterThan(0);
  }

  // Click first Add to cart and expect navigation to cart
  const btn = await page.$('.gd-market-add-to-cart');
  expect(btn).not.toBeNull();

  await Promise.all([
    page.waitForNavigation({ url: '**/cart/**', timeout: 10000 }),
    btn.click()
  ]);

  expect(page.url()).toContain('/cart/');
});
