const fs = require('fs');
const path = require('path');
const puppeteer = require('puppeteer');

(async () => {
  const svgPath = path.resolve(__dirname, 'database-erd-connected.svg');
  const outPath = path.resolve(__dirname, '../public/images/database-erd-connected.png');
  const svg = fs.readFileSync(svgPath, 'utf8');
  const html = `<!DOCTYPE html><html><head><style>body{margin:0;background:#f8fafc}</style></head><body>${svg}</body></html>`;

  const browser = await puppeteer.launch({ headless: 'new', args: ['--no-sandbox'] });
  const page = await browser.newPage();
  await page.setViewport({ width: 2100, height: 1794, deviceScaleFactor: 2 });
  await page.setContent(html, { waitUntil: 'networkidle0' });
  await page.screenshot({ path: outPath, fullPage: true, type: 'png' });
  await browser.close();
  console.log('PNG written:', outPath);
})().catch((err) => {
  console.error(err);
  process.exit(1);
});
