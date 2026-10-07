import { config } from '../config.js';

type ConsoleEntry = { type: string; text: string };

type BrowserModule = typeof import('playwright');

async function loadPlaywright(): Promise<BrowserModule> {
  if (!config.browserTesting) {
    throw new Error('Browser testing is disabled. Set WPGPTVIBE_BROWSER_TESTING=true to enable it.');
  }
  try {
    return await import('playwright');
  } catch {
    throw new Error('Playwright is not installed. Install optional dependencies and browser binaries before enabling browser testing.');
  }
}

async function withPage<T>(url: string, callback: (page: import('playwright').Page, consoleEntries: ConsoleEntry[], pageErrors: string[]) => Promise<T>): Promise<T> {
  const playwright = await loadPlaywright();
  const browser = await playwright.chromium.launch({ headless: true });
  try {
    const page = await browser.newPage();
    page.setDefaultTimeout(config.browserTimeoutMs);
    const consoleEntries: ConsoleEntry[] = [];
    const pageErrors: string[] = [];
    page.on('console', (message) => {
      if (consoleEntries.length < 100) {
        consoleEntries.push({ type: message.type(), text: message.text().slice(0, 1000) });
      }
    });
    page.on('pageerror', (error) => {
      if (pageErrors.length < 50) pageErrors.push(error.message.slice(0, 2000));
    });
    await page.goto(url, { waitUntil: 'domcontentloaded', timeout: config.browserTimeoutMs });
    return await callback(page, consoleEntries, pageErrors);
  } finally {
    await browser.close();
  }
}

export async function testPage(url: string, selectors: string[] = []) {
  return withPage(url, async (page, consoleEntries, pageErrors) => {
    const selectorResults: Record<string, boolean> = {};
    for (const selector of selectors.slice(0, 50)) {
      selectorResults[selector] = await page.locator(selector).first().isVisible().catch(() => false);
    }
    return {
      url: page.url(),
      title: await page.title(),
      status_ok: true,
      selectors: selectorResults,
      console_errors: consoleEntries.filter((entry) => entry.type === 'error'),
      page_errors: pageErrors,
    };
  });
}

export async function scanConsoleErrors(url: string) {
  return withPage(url, async (page, consoleEntries, pageErrors) => ({
    url: page.url(),
    console_errors: consoleEntries.filter((entry) => entry.type === 'error'),
    console_warnings: consoleEntries.filter((entry) => entry.type === 'warning'),
    page_errors: pageErrors,
  }));
}

export async function testRoutes(urls: string[]) {
  const results = [];
  for (const url of urls.slice(0, 50)) {
    try {
      results.push({ ok: true, ...(await testPage(url)) });
    } catch (error) {
      results.push({ ok: false, url, error: error instanceof Error ? error.message : 'Unknown browser error' });
    }
  }
  return { total: results.length, results };
}

export async function testCalculatorUI(url: string, input: {
  ready_selector?: string;
  fields?: Array<{ selector: string; value: string }>;
  calculate_selector?: string;
  reset_selector?: string;
  output_selectors?: string[];
}) {
  return withPage(url, async (page, consoleEntries, pageErrors) => {
    if (input.ready_selector) {
      await page.locator(input.ready_selector).waitFor({ state: 'visible' });
    }

    for (const field of (input.fields ?? []).slice(0, 30)) {
      const locator = page.locator(field.selector).first();
      await locator.fill(String(field.value));
    }

    if (input.calculate_selector) {
      await page.locator(input.calculate_selector).first().click();
    }

    const outputs: Record<string, string> = {};
    for (const selector of (input.output_selectors ?? []).slice(0, 30)) {
      outputs[selector] = (await page.locator(selector).first().textContent().catch(() => null))?.trim() ?? '';
    }

    let reset_ok: boolean | null = null;
    if (input.reset_selector) {
      reset_ok = await page.locator(input.reset_selector).first().click().then(() => true).catch(() => false);
    }

    return {
      url: page.url(),
      outputs,
      reset_ok,
      console_errors: consoleEntries.filter((entry) => entry.type === 'error'),
      page_errors: pageErrors,
    };
  });
}
