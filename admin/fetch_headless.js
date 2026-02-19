// fetch_headless.js
// Usage: node fetch_headless.js <url>
// Requires: npm install puppeteer

const puppeteer = require('puppeteer');

(async () => {
  const url = process.argv[2];
  if (!url) {
    console.error(JSON.stringify({ success: false, message: 'No URL provided' }));
    process.exit(2);
  }

  let browser;
  try {
    browser = await puppeteer.launch({ args: ['--no-sandbox', '--disable-setuid-sandbox'] });
    const page = await browser.newPage();
    await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/115.0 Safari/537.36');
    await page.setViewport({ width: 1280, height: 800 });

    // go to the page and wait until network quiet
    await page.goto(url, { waitUntil: 'networkidle2', timeout: 45000 });

    // small delay to allow lazy content to load
    await page.waitForTimeout(1200);

    const html = await page.content();
    await browser.close();

    // output JSON with HTML in 'html' field
    console.log(JSON.stringify({ success: true, html }));
    process.exit(0);
  } catch (err) {
    try { if (browser) { await browser.close(); } } catch (e) {}
    console.error(JSON.stringify({ success: false, message: String(err && err.message ? err.message : err) }));
    process.exit(3);
  }
})();
