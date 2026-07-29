const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

async function quickCompare() {
  console.log('🔍 Quick Visual Comparison\n');

  const browser = await chromium.launch({ headless: false });
  const context = await browser.newContext({ viewport: { width: 1920, height: 1080 } });

  try {
    // Capture static design hero
    console.log('📸 Capturing static design...');
    const staticPage = await context.newPage();
    await staticPage.goto('file://' + path.resolve(__dirname, '../ridemaster-design/index.html'));
    await staticPage.waitForTimeout(2000);

    const staticHero = staticPage.locator('section.hero');
    await staticHero.screenshot({ path: path.join(__dirname, 'screenshots', 'static-hero.png') });
    console.log('  ✅ Static hero captured\n');

    // Capture Elementor hero - use the latest created page
    console.log('📸 Capturing Elementor template...');
    const elementorPage = await context.newPage();
    await elementorPage.goto('https://staging4.ridemaster.eu/?p=351'); // Update with actual ID
    await elementorPage.waitForTimeout(3000);

    const elementorHero = elementorPage.locator('.elementor-location-header').first();
    await elementorHero.screenshot({ path: path.join(__dirname, 'screenshots', 'elementor-hero.png') });
    console.log('  ✅ Elementor hero captured\n');

    console.log('📊 Visual Analysis:\n');
    console.log('Screenshots saved to screenshots/ folder');
    console.log('  - static-hero.png');
    console.log('  - elementor-hero.png');
    console.log('\nCompare these manually to identify differences.\n');

    await new Promise(resolve => setTimeout(resolve, 60000));
  } finally {
    await browser.close();
  }
}

quickCompare().catch(console.error);
