/**
 * Import Hero template via Elementor interface and validate
 * Flow: Open page → Folder icon → My Templates → Upload icon → Select file → Insert → Update
 */

const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const CONFIG = {
  loginURL: 'https://staging4.ridemaster.eu/wp-login.php',
  wpAdminURL: 'https://staging4.ridemaster.eu/wp-admin',
  baseURL: 'https://staging4.ridemaster.eu',
  username: 'xavierkain.consulting@gmail.com',
  password: '8Bc99WVWc4!zmN@fqdd!',
};

async function importAndTestHero() {
  console.log('🚀 Importing and Testing Hero Template...\n');

  const browser = await chromium.launch({
    headless: false, // Show browser for debugging
    slowMo: 500, // Slow down actions to see what happens
  });

  const context = await browser.newContext({
    viewport: { width: 1920, height: 1080 },
  });
  const page = await context.newPage();

  try {
    const templatePath = path.join(__dirname, 'generated-templates', 'hero-auto-v1.json');

    // Step 1: Login
    console.log('📝 Logging in...');
    await page.goto(CONFIG.loginURL);
    await page.waitForSelector('input[name="log"]');
    await page.fill('input[name="log"]', CONFIG.username);
    await page.fill('input[name="pwd"]', CONFIG.password);
    await page.click('input[name="wp-submit"]');
    await page.waitForTimeout(5000);
    console.log('  ✅ Logged in\n');

    // Step 2: Create a new page or use existing test page
    console.log('📄 Creating/Opening test page...');

    // Try to find existing "Hero Test" page first
    await page.goto(`${CONFIG.wpAdminURL}/edit.php?post_type=page`);
    await page.waitForTimeout(2000);

    const existingPage = await page.locator('.row-title:has-text("Hero Test Automation")').first().catch(() => null);

    if (existingPage) {
      console.log('  Found existing test page, opening it...');
      await existingPage.click();
      await page.waitForTimeout(2000);
    } else {
      console.log('  Creating new test page...');
      await page.goto(`${CONFIG.wpAdminURL}/post-new.php?post_type=page`);
      await page.waitForTimeout(3000);

      // Fill title
      const titleInput = page.locator('.editor-post-title__input, [name="post_title"]').first();
      await titleInput.fill('Hero Test Automation');
      await page.waitForTimeout(1000);
    }

    // Step 3: Open Elementor editor
    console.log('🎨 Opening Elementor editor...');

    // Look for "Edit with Elementor" button
    const editButton = page.locator('a:has-text("Edit with Elementor"), button:has-text("Edit with Elementor")').first();
    await editButton.click();
    await page.waitForTimeout(10000); // Wait for Elementor to fully load
    console.log('  ✅ Elementor editor opened\n');

    // Step 4: Click folder icon (Add Template)
    console.log('📁 Opening template library...');

    // The folder icon in Elementor panel footer
    await page.click('#elementor-panel-footer-add-template, .elementor-add-template-button');
    await page.waitForTimeout(3000);
    console.log('  ✅ Template library opened\n');

    // Step 5: Go to "My Templates" tab
    console.log('📋 Switching to My Templates tab...');
    await page.click('text=My Templates, #elementor-template-library-templates-my_templates').catch(async () => {
      // Try alternative selector
      await page.click('[data-tab="my_templates"], .elementor-template-library-menu-item:has-text("My Templates")');
    });
    await page.waitForTimeout(2000);
    console.log('  ✅ On My Templates tab\n');

    // Step 6: Click upload icon (arrow up)
    console.log('⬆️  Clicking upload button...');
    await page.click('.elementor-template-library-template-action:has-text("Import"), button:has-text("Import")').catch(async () => {
      // Try alternative - look for upload/import icon
      await page.click('.elementor-template-library-toolbar-import, #elementor-template-library-toolbar-import');
    });
    await page.waitForTimeout(2000);
    console.log('  ✅ Upload dialog opened\n');

    // Step 7: Upload file
    console.log('📤 Uploading template file...');

    const fileInput = await page.locator('input[type="file"]').first();
    await fileInput.setInputFiles(templatePath);
    await page.waitForTimeout(3000);
    console.log('  ✅ File selected\n');

    // Step 8: Click Insert button
    console.log('✅ Clicking Insert button...');
    await page.click('button:has-text("Insert"), .elementor-button-success:has-text("Insert")').catch(async () => {
      // Alternative: Import Now button
      await page.click('button:has-text("Import Now"), .dialog-insert-button');
    });
    await page.waitForTimeout(5000);
    console.log('  ✅ Template inserted!\n');

    // Step 9: Update/Publish the page
    console.log('💾 Saving page...');
    await page.click('#elementor-panel-saver-button-publish, #elementor-panel-saver-button-save-options').catch(async () => {
      await page.click('.elementor-button-success:has-text("Update"), .elementor-button-success:has-text("Publish")');
    });
    await page.waitForTimeout(5000);
    console.log('  ✅ Page saved\n');

    // Step 10: Get preview URL and navigate to frontend
    console.log('🔍 Navigating to frontend for validation...');

    // Click "View Page" or get URL from Elementor
    const viewPageButton = page.locator('a:has-text("View Page"), .elementor-panel-footer-settings a').first();
    const pageURL = await viewPageButton.getAttribute('href').catch(() => null);

    if (pageURL) {
      await page.goto(pageURL);
    } else {
      // Fallback: try to construct URL
      await page.goto(`${CONFIG.baseURL}/hero-test-automation/`);
    }

    await page.waitForTimeout(5000);
    console.log('  ✅ Frontend loaded\n');

    // Step 11: Take screenshot
    console.log('📸 Taking screenshot...');
    const screenshot = await page.screenshot({ fullPage: true });
    fs.writeFileSync(path.join(__dirname, 'screenshots', 'hero-frontend-validated.png'), screenshot);
    console.log('  ✅ Screenshot saved\n');

    // Step 12: Validate rendering
    console.log('🔍 Validating rendering...\n');
    const validation = await page.evaluate(() => {
      const results = {
        success: true,
        checks: {},
        errors: [],
      };

      // Check 1: Title exists and is white
      const title = document.querySelector('h1');
      results.checks.title = {
        found: !!title,
        text: title?.textContent?.trim() || '',
        color: title ? window.getComputedStyle(title).color : '',
        fontSize: title ? window.getComputedStyle(title).fontSize : '',
      };

      if (!title || !title.textContent.includes('Find Your Next Camp')) {
        results.errors.push('Title not found or incorrect text');
        results.success = false;
      }

      const titleColor = title ? window.getComputedStyle(title).color : '';
      if (titleColor !== 'rgb(255, 255, 255)') {
        results.errors.push(`Title color is ${titleColor}, expected white (rgb(255, 255, 255))`);
        results.success = false;
      }

      // Check 2: Subtitle
      const paragraphs = Array.from(document.querySelectorAll('p'));
      const subtitle = paragraphs.find(p => p.textContent.includes('Discover world-class'));
      results.checks.subtitle = {
        found: !!subtitle,
        text: subtitle?.textContent?.trim() || '',
      };

      if (!subtitle) {
        results.errors.push('Subtitle not found');
        results.success = false;
      }

      // Check 3: Trust badges - should be 3
      const iconBoxes = document.querySelectorAll('.elementor-icon-box-wrapper');
      results.checks.trustBadges = {
        count: iconBoxes.length,
        titles: Array.from(iconBoxes).map(box => {
          const title = box.querySelector('.elementor-icon-box-title');
          return title?.textContent?.trim() || '';
        }),
      };

      if (iconBoxes.length !== 3) {
        results.errors.push(`Expected 3 trust badges, found ${iconBoxes.length}`);
        results.success = false;
      }

      // Check 4: FontAwesome icons
      const icons = document.querySelectorAll('.fa-shield-alt, .fa-lock, .fa-sync-alt');
      results.checks.icons = {
        count: icons.length,
        types: Array.from(icons).map(icon => {
          return Array.from(icon.classList).find(c => c.startsWith('fa-'));
        }),
      };

      if (icons.length !== 3) {
        results.errors.push(`Expected 3 FontAwesome icons, found ${icons.length}`);
        results.success = false;
      }

      // Check 5: Background image
      const hero = document.querySelector('[data-id="hero-main"], .elementor-element');
      if (hero) {
        const bgImage = window.getComputedStyle(hero).backgroundImage;
        results.checks.background = {
          hasImage: bgImage !== 'none' && bgImage.includes('unsplash'),
        };

        if (!bgImage.includes('unsplash')) {
          results.errors.push('Background image not found or incorrect');
          results.success = false;
        }
      }

      // Check 6: Trust badges layout - should be ROW not column
      const trustContainer = document.querySelector('[data-id="hero-trust"]');
      if (trustContainer) {
        const flexDirection = window.getComputedStyle(trustContainer).flexDirection;
        results.checks.trustLayout = {
          flexDirection: flexDirection,
        };

        if (flexDirection !== 'row') {
          results.errors.push(`Trust badges layout is ${flexDirection}, expected row`);
          results.success = false;
        }
      }

      return results;
    });

    // Display results
    console.log('═'.repeat(80));
    console.log('VALIDATION RESULTS');
    console.log('═'.repeat(80));
    console.log(`\n✅ Overall: ${validation.success ? 'PASSED' : 'FAILED'}\n`);

    console.log('📋 Checks:');
    console.log('─'.repeat(80));
    console.log(`Title: ${validation.checks.title?.found ? '✅' : '❌'}`);
    console.log(`  Text: "${validation.checks.title?.text}"`);
    console.log(`  Color: ${validation.checks.title?.color}`);
    console.log(`  Font Size: ${validation.checks.title?.fontSize}`);

    console.log(`\nSubtitle: ${validation.checks.subtitle?.found ? '✅' : '❌'}`);
    console.log(`  Text: "${validation.checks.subtitle?.text?.substring(0, 60)}..."`);

    console.log(`\nTrust Badges: ${validation.checks.trustBadges?.count === 3 ? '✅' : '❌'}`);
    console.log(`  Count: ${validation.checks.trustBadges?.count}`);
    console.log(`  Titles: ${validation.checks.trustBadges?.titles?.join(', ')}`);

    console.log(`\nFontAwesome Icons: ${validation.checks.icons?.count === 3 ? '✅' : '❌'}`);
    console.log(`  Count: ${validation.checks.icons?.count}`);
    console.log(`  Types: ${validation.checks.icons?.types?.join(', ')}`);

    console.log(`\nBackground: ${validation.checks.background?.hasImage ? '✅' : '❌'}`);

    console.log(`\nTrust Badges Layout: ${validation.checks.trustLayout?.flexDirection === 'row' ? '✅' : '❌'}`);
    console.log(`  Flex Direction: ${validation.checks.trustLayout?.flexDirection}`);

    if (validation.errors.length > 0) {
      console.log('\n❌ Errors:');
      validation.errors.forEach(err => console.log(`  - ${err}`));
    }

    console.log('\n' + '═'.repeat(80));

    // Save validation results
    fs.writeFileSync(
      path.join(__dirname, 'hero-validation-results.json'),
      JSON.stringify(validation, null, 2)
    );
    console.log('\n💾 Validation results saved to: hero-validation-results.json');

    if (validation.success) {
      console.log('\n🎉 SUCCESS! Hero template is working perfectly!\n');
    } else {
      console.log('\n⚠️  Some issues detected. Check validation results above.\n');
    }

    console.log('⏸️  Browser will stay open for 30 seconds for manual inspection...');
    await page.waitForTimeout(30000);

  } catch (error) {
    console.error('\n❌ Error occurred:', error.message);
    console.log('\nTaking error screenshot...');
    const errorScreenshot = await page.screenshot({ fullPage: true });
    fs.writeFileSync(path.join(__dirname, 'screenshots', 'error-screenshot.png'), errorScreenshot);
    console.log('Error screenshot saved.\n');

    console.log('Browser will stay open for debugging...');
    await page.waitForTimeout(60000);
  } finally {
    await browser.close();
  }
}

importAndTestHero().catch(console.error);
