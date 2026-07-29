/**
 * Inspect existing camp to see what fields are available
 */

const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const CONFIG = {
  loginURL: 'https://staging4.ridemaster.eu/wp-login.php',
  wpAdminURL: 'https://staging4.ridemaster.eu/wp-admin',
  username: 'xavierkain.consulting@gmail.com',
  password: '8Bc99WVWc4!zmN@fqdd!',
};

async function inspectCamp() {
  console.log('🔍 Inspecting existing camp...\n');

  const browser = await chromium.launch({ headless: false }); // Non-headless pour debug
  const context = await browser.newContext({
    viewport: { width: 1920, height: 1080 },
  });
  const page = await context.newPage();

  try {
    // Login
    console.log('📝 Logging in...');
    await page.goto(CONFIG.loginURL);
    await page.fill('input[name="log"]', CONFIG.username);
    await page.fill('input[name="pwd"]', CONFIG.password);
    await page.click('input[name="wp-submit"]');
    await page.waitForURL(/wp-admin/);
    console.log('✅ Logged in\n');

    // Go to camps list
    console.log('📋 Going to camps list...');
    await page.goto(`${CONFIG.wpAdminURL}/edit.php?post_type=camp`);
    await page.waitForTimeout(2000);

    // Take screenshot
    let screenshot = await page.screenshot({ fullPage: true });
    fs.writeFileSync(path.join(__dirname, 'screenshots', 'camps-list-detail.png'), screenshot);
    console.log('  📸 Saved: camps-list-detail.png');

    // Click on first camp to edit
    console.log('\n✏️  Opening camp for editing...');
    const firstCampLink = page.locator('.row-title').first();
    await firstCampLink.click();
    await page.waitForTimeout(3000);

    // Take screenshot of edit page
    screenshot = await page.screenshot({ fullPage: true });
    fs.writeFileSync(path.join(__dirname, 'screenshots', 'camp-edit-full.png'), screenshot);
    console.log('  📸 Saved: camp-edit-full.png');

    // Scroll down to see custom fields
    await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
    await page.waitForTimeout(1000);
    screenshot = await page.screenshot({ fullPage: true });
    fs.writeFileSync(path.join(__dirname, 'screenshots', 'camp-edit-bottom.png'), screenshot);
    console.log('  📸 Saved: camp-edit-bottom.png');

    // Try to find ACF fields or other custom fields
    console.log('\n🔎 Looking for custom fields...');
    const customFieldsInfo = await page.evaluate(() => {
      const results = {
        acfFields: [],
        metaBoxes: [],
        elementorData: null,
      };

      // Check for ACF
      const acfFields = document.querySelectorAll('.acf-field');
      results.acfFields = Array.from(acfFields).map(field => {
        const label = field.querySelector('.acf-label label');
        const input = field.querySelector('input, textarea, select');
        return {
          label: label?.textContent?.trim() || '',
          name: input?.name || field.dataset.name || '',
          type: field.dataset.type || input?.type || '',
        };
      });

      // Check for regular meta boxes
      const metaBoxes = document.querySelectorAll('.postbox');
      results.metaBoxes = Array.from(metaBoxes).map(box => {
        const title = box.querySelector('.hndle, .postbox-header h2');
        return {
          title: title?.textContent?.trim() || '',
          id: box.id || '',
        };
      });

      return results;
    });

    console.log('\nCustom Fields Found:');
    console.log(JSON.stringify(customFieldsInfo, null, 2));

    // Save to file
    fs.writeFileSync(
      path.join(__dirname, 'camp-fields-analysis.json'),
      JSON.stringify(customFieldsInfo, null, 2)
    );
    console.log('\n💾 Saved analysis to: camp-fields-analysis.json');

    console.log('\n⏸️  Pausing for manual inspection (browser will stay open for 60 seconds)...');
    console.log('Check the browser window to see the camp edit page');
    await page.waitForTimeout(60000);

  } catch (error) {
    console.error('❌ Error:', error);
  } finally {
    await browser.close();
  }
}

// Create screenshots directory
if (!fs.existsSync(path.join(__dirname, 'screenshots'))) {
  fs.mkdirSync(path.join(__dirname, 'screenshots'));
}

inspectCamp().catch(console.error);
