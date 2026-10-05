import { test, expect } from './fixtures';

test('public site: localized navigation, SEO, blog, mobile menu and auth links', async ({ page }) => {
  for (const locale of ['ru', 'ro', 'en']) {
    await page.goto('/' + locale);
    await expect(page.locator('html')).toHaveAttribute('lang', locale);
    await expect(page.locator('h1')).toHaveCount(1);
    await expect(page.locator('link[rel="canonical"]')).toHaveAttribute('href', new RegExp('/' + locale + '$'));
    await expect(page.locator('meta[property="og:image"]')).toHaveAttribute('content', /social-preview.png$/);
    await expect(page.locator('a[href="mailto:contact@noros.net"]')).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBeTruthy();
    await page.goto('/' + locale + '/blog');
    await expect(page.locator('.post-card')).toHaveCount(3);
    await page.locator('.post-card h3 a').first().click();
    await expect(page.locator('.article h1')).toBeVisible();
    await expect(page.locator('meta[property="og:type"]')).toHaveAttribute('content', 'article');
  }
  await page.goto('/ru');
  if (await page.locator('.menu-toggle').isVisible()) {
    await page.locator('.menu-toggle').click();
    await expect(page.locator('#mobile-menu')).toBeVisible();
    await page.locator('#mobile-menu').getByRole('link', {name:'Возможности', exact:true}).click();
    await expect(page).toHaveURL(/\/ru\/features$/);
  } else {
    await page.locator('.desktop-nav').getByRole('link',{name:'Возможности',exact:true}).click();
    await expect(page).toHaveURL(/\/ru\/features$/);
  }
  await page.goto('/ru/missing-page');
  await expect(page.getByText('404', {exact:true})).toBeVisible();
  await page.goto('/ru');
  await page.locator('.header-actions a[href$="/register"]').click();
  await expect(page).toHaveURL(/\/register$/);
  await expect(page.locator('input[type="email"]')).toBeVisible();
  await page.locator('.auth-links a[href="/"]').click();
  await expect(page.locator('.hero h1')).toBeVisible();
});

test('public site: six viewport sizes keep all content within the screen', async ({page}) => {
  for (const width of [360,390,430,768,1024,1440]) {
    await page.setViewportSize({width,height:1000});
    await page.goto('/ru');
    await expect(page.locator('.hero h1')).toBeVisible();
    expect(await page.evaluate(() => ({width:innerWidth,content:document.documentElement.scrollWidth}))).toEqual({width,content:width});
    await page.screenshot({path:`../docs/qa/public-site-${width}.png`,fullPage:true});
  }
});

test('public site: application session survives public pages and logout returns home', async ({page, context}) => {
  await page.goto('/login');
  await page.getByLabel('Email', {exact:true}).fill('dev@norocel.test');
  await page.getByLabel('Parolă', {exact:true}).fill('local-testing-123');
  await page.getByRole('button',{name:'Autentificare',exact:true}).click();
  await expect(page).toHaveURL(/\/app$/);
  await expect(page.locator('.currency-balance')).toHaveCount(4);
  await page.waitForLoadState('networkidle');
  expect((await context.cookies()).find(cookie => cookie.name === 'XSRF-TOKEN')?.value).toBeTruthy();
  const articleResponse = await page.goto('/ru/blog/budget-without-pressure');
  expect(articleResponse?.headers()['set-cookie'] ?? '').not.toContain('XSRF-TOKEN=');
  expect(await page.evaluate(async () => {
    const token = decodeURIComponent(document.cookie.split('; ').find(cookie => cookie.startsWith('XSRF-TOKEN='))?.slice(11) ?? '');
    return (await fetch('/api/v1/me',{method:'PATCH',credentials:'include',headers:{Accept:'application/json','Content-Type':'application/json','X-XSRF-TOKEN':token,'Idempotency-Key':crypto.randomUUID()},body:JSON.stringify({locale:'ro'})})).status;
  })).toBe(200);
  expect((await page.request.get('/api/v1/me')).status()).toBe(200);
  await page.goto('/login');
  await expect(page).toHaveURL(/\/app$/);
  await page.goto('/register');
  await expect(page).toHaveURL(/\/app$/);
  await page.goto('/app');
  await expect(page.locator('.currency-balance')).toHaveCount(4);
  await page.goto('/settings');
  await page.getByRole('main').getByRole('button',{name:'Ieșire',exact:true}).click();
  await expect(page.locator('.hero h1')).toBeVisible();
  expect((await page.request.get('/api/v1/me')).status()).toBe(401);
});
