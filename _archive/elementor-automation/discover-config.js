/**
 * WordPress Configuration Discovery Script
 * Uses Playwright to log into WP Admin and extract configuration
 */

const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const CONFIG = {
  baseURL: 'https://staging4.ridemaster.eu',
  loginURL: 'https://staging4.ridemaster.eu/wp-login.php',
  wpAdminURL: 'https://staging4.ridemaster.eu/wp-admin',
  username: 'xavierkain.consulting@gmail.com',
  password: '8Bc99WVWc4!zmN@fqdd!',
};

async function discoverConfiguration() {
  console.log('🚀 Starting WordPress Configuration Discovery...\n');

  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({
    viewport: { width: 1920, height: 1080 },
  });
  const page = await context.newPage();

  try {
    // Step 1: Login to WordPress
    console.log('📝 Logging into WordPress Admin...');
    await page.goto(CONFIG.loginURL, { waitUntil: 'load', timeout: 30000 });

    // Debug: Take screenshot
    const loginScreenshot = await page.screenshot({ fullPage: true });
    fs.writeFileSync(path.join(__dirname, 'screenshots', 'login-page.png'), loginScreenshot);
    console.log('  📸 Login page screenshot saved');

    // Wait and fill login form
    await page.waitForSelector('input[name="log"]', { timeout: 10000 });
    await page.fill('input[name="log"]', CONFIG.username);
    await page.fill('input[name="pwd"]', CONFIG.password);
    await page.click('input[name="wp-submit"]');

    // Wait for dashboard
    await page.waitForURL(/wp-admin/, { timeout: 10000 });
    console.log('✅ Logged in successfully!\n');

    const results = {
      timestamp: new Date().toISOString(),
      elementor: {},
      jetengine: {},
      postTypes: {},
      taxonomies: {},
    };

    // Step 2: Discover JetEngine Post Types
    console.log('🔍 Discovering JetEngine Post Types...');
    try {
      await page.goto(`${CONFIG.wpAdminURL}/admin.php?page=jet-engine-cpt`);
      await page.waitForTimeout(2000);

      const screenshot = await page.screenshot({ fullPage: true });
      fs.writeFileSync(path.join(__dirname, 'screenshots', 'jetengine-cpt.png'), screenshot);
      console.log('  📸 Screenshot saved: jetengine-cpt.png');

      // Extract CPT data from the page
      const cptData = await page.evaluate(() => {
        const rows = Array.from(document.querySelectorAll('.jet-cpt-list tr, .jet-engine-cpt-list tr, table.wp-list-table tr'));
        return rows.map(row => {
          const cells = row.querySelectorAll('td');
          if (cells.length === 0) return null;
          return {
            name: cells[0]?.textContent?.trim() || '',
            slug: cells[1]?.textContent?.trim() || '',
          };
        }).filter(Boolean);
      });

      results.jetengine.postTypes = cptData;
      console.log(`  Found ${cptData.length} custom post types`);
    } catch (error) {
      console.log('  ⚠️  Could not access JetEngine CPT page:', error.message);
    }

    // Step 3: Discover Elementor Global Colors
    console.log('\n🎨 Discovering Elementor Global Colors...');
    try {
      await page.goto(`${CONFIG.wpAdminURL}/admin.php?page=elementor#tab-globals`);
      await page.waitForTimeout(3000);

      const screenshot = await page.screenshot({ fullPage: true });
      fs.writeFileSync(path.join(__dirname, 'screenshots', 'elementor-globals.png'), screenshot);
      console.log('  📸 Screenshot saved: elementor-globals.png');
    } catch (error) {
      console.log('  ⚠️  Could not access Elementor globals:', error.message);
    }

    // Step 4: Check for existing templates
    console.log('\n📋 Discovering existing Elementor templates...');
    try {
      await page.goto(`${CONFIG.wpAdminURL}/edit.php?post_type=elementor_library`);
      await page.waitForTimeout(2000);

      const screenshot = await page.screenshot({ fullPage: true });
      fs.writeFileSync(path.join(__dirname, 'screenshots', 'elementor-templates.png'), screenshot);
      console.log('  📸 Screenshot saved: elementor-templates.png');

      const templates = await page.evaluate(() => {
        const rows = Array.from(document.querySelectorAll('.wp-list-table tbody tr'));
        return rows.map(row => {
          const titleEl = row.querySelector('.row-title');
          const typeEl = row.querySelector('.elementor-template-library-template-type');
          return {
            title: titleEl?.textContent?.trim() || '',
            type: typeEl?.textContent?.trim() || '',
          };
        }).filter(item => item.title);
      });

      results.elementor.existingTemplates = templates;
      console.log(`  Found ${templates.length} existing templates`);
    } catch (error) {
      console.log('  ⚠️  Could not access templates:', error.message);
    }

    // Step 5: Check for existing camps
    console.log('\n🏕️  Checking for existing camp posts...');
    try {
      await page.goto(`${CONFIG.wpAdminURL}/edit.php?post_type=camp`);
      await page.waitForTimeout(2000);

      const screenshot = await page.screenshot({ fullPage: true });
      fs.writeFileSync(path.join(__dirname, 'screenshots', 'camp-posts.png'), screenshot);
      console.log('  📸 Screenshot saved: camp-posts.png');

      const camps = await page.evaluate(() => {
        const rows = Array.from(document.querySelectorAll('.wp-list-table tbody tr'));
        return rows.map(row => {
          const titleEl = row.querySelector('.row-title');
          return titleEl?.textContent?.trim() || '';
        }).filter(Boolean);
      });

      results.postTypes.camp = { count: camps.length, titles: camps.slice(0, 5) };
      console.log(`  Found ${camps.length} camp posts`);
      if (camps.length > 0) {
        console.log(`  First few: ${camps.slice(0, 3).join(', ')}`);
      }
    } catch (error) {
      console.log('  ⚠️  Could not access camp posts:', error.message);
    }

    // Step 6: Get JetEngine Meta Fields
    console.log('\n⚙️  Discovering JetEngine Meta Fields for Camp...');
    try {
      await page.goto(`${CONFIG.wpAdminURL}/admin.php?page=jet-engine-meta&cpt=camp`);
      await page.waitForTimeout(2000);

      const screenshot = await page.screenshot({ fullPage: true });
      fs.writeFileSync(path.join(__dirname, 'screenshots', 'jetengine-meta-fields.png'), screenshot);
      console.log('  📸 Screenshot saved: jetengine-meta-fields.png');
    } catch (error) {
      console.log('  ⚠️  Could not access meta fields:', error.message);
    }

    // Save results
    const outputPath = path.join(__dirname, 'discovery-results.json');
    fs.writeFileSync(outputPath, JSON.stringify(results, null, 2));
    console.log(`\n💾 Discovery results saved to: ${outputPath}`);

    console.log('\n✅ Discovery completed successfully!');

  } catch (error) {
    console.error('❌ Error during discovery:', error);
    throw error;
  } finally {
    await browser.close();
  }
}

// Create screenshots directory
if (!fs.existsSync(path.join(__dirname, 'screenshots'))) {
  fs.mkdirSync(path.join(__dirname, 'screenshots'));
}

// Run discovery
discoverConfiguration().catch(console.error);
