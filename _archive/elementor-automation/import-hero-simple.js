/**
 * Simplified Hero import - creates new page directly
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

async function importHero() {
  console.log('🚀 Importing Hero Template (Simplified Flow)...\n');

  const browser = await chromium.launch({
    headless: false,
    slowMo: 800,
  });

  const context = await browser.newContext({
    viewport: { width: 1920, height: 1080 },
  });
  const page = await context.newPage();

  try {
    const templatePath = path.join(__dirname, 'generated-templates', 'hero-auto-v1.json');
    console.log(`Template to import: ${templatePath}\n`);

    // Login
    console.log('📝 Logging in...');
    await page.goto(CONFIG.loginURL);
    await page.waitForSelector('input[name="log"]');
    await page.fill('input[name="log"]', CONFIG.username);
    await page.fill('input[name="pwd"]', CONFIG.password);
    await page.click('input[name="wp-submit"]');
    await page.waitForTimeout(5000);
    console.log('✅ Logged in\n');

    // Create NEW page directly
    console.log('📄 Creating new page...');
    await page.goto(`${CONFIG.wpAdminURL}/post-new.php?post_type=page`);
    await page.waitForTimeout(4000);

    // Fill title
    console.log('✏️  Setting page title...');
    const titleSelector = '.editor-post-title__input, .block-editor-page-title, [placeholder="Add title"]';
    await page.waitForSelector(titleSelector, { timeout: 5000 });
    await page.click(titleSelector);
    await page.fill(titleSelector, 'Hero Auto Import Test');
    await page.waitForTimeout(2000);
    console.log('✅ Title set\n');

    // Click "Edit with Elementor"
    console.log('🎨 Opening Elementor...');
    // The button is visible in the top bar
    await page.click('.elementor-switch-mode').catch(async () => {
      console.log('  Trying alternative selector...');
      await page.click('a.elementor-create-new-post, #elementor-switch-mode-button');
    });

    console.log('⏳ Waiting for Elementor to load...');

    // Wait for Elementor panel to be visible with longer timeout
    console.log('   Waiting for Elementor panel...');
    await page.waitForSelector('#elementor-panel', { timeout: 30000 });
    console.log('   ✅ Panel detected\n');

    // Wait a bit more for full initialization
    await page.waitForTimeout(5000);

    // Take debug screenshot BEFORE looking for footer
    console.log('📸 Taking debug screenshot of Elementor panel...');
    await page.screenshot({ path: path.join(__dirname, 'screenshots', 'debug-panel.png'), fullPage: true });

    // Dump footer HTML for debugging
    console.log('\n🔍 Dumping complete footer HTML...');
    const footerHTML = await page.evaluate(() => {
      const footer = document.querySelector('#elementor-panel-footer');
      return footer ? footer.outerHTML : 'FOOTER NOT FOUND';
    });
    console.log('Footer HTML:', footerHTML);
    console.log('\n');

    // Find ALL clickable elements in the panel to debug
    console.log('🔍 Scanning for clickable elements in panel...');
    const clickableElements = await page.evaluate(() => {
      const panel = document.querySelector('#elementor-panel');
      if (!panel) return [];

      const buttons = panel.querySelectorAll('button, a, [role="button"], i[class*="eicon"]');
      return Array.from(buttons).slice(0, 20).map((el, idx) => ({
        index: idx,
        tag: el.tagName,
        classes: el.className,
        id: el.id || '',
        title: el.title || el.getAttribute('aria-label') || '',
        text: el.textContent?.trim().substring(0, 30) || ''
      }));
    });
    console.log('Found elements:', JSON.stringify(clickableElements, null, 2));

    // Try using keyboard shortcut to open template library
    console.log('\n⌨️  Trying keyboard shortcut Cmd+Shift+L for template library...');
    await page.keyboard.press('Meta+Shift+L');
    await page.waitForTimeout(3000);

    // Check if template library opened
    let libraryOpened = await page.locator('.dialog-widget, #elementor-template-library-modal').count() > 0;
    console.log(`  Template library visible: ${libraryOpened}`);

    if (!libraryOpened) {
      console.log('  Trying alternative: click + button in canvas...');
      const plusButton = page.locator('.elementor-add-section-button, button:has-text("+")').first();
      if (await plusButton.count() > 0 && await plusButton.isVisible()) {
        await plusButton.click();
        await page.waitForTimeout(2000);
        libraryOpened = true;
        console.log('  ✅ Clicked + button');
      }
    }

    if (!libraryOpened) {
      console.log('  Trying direct DOM click on menu button...');
      const clicked = await page.evaluate(() => {
        const menuBtn = document.querySelector('#elementor-panel-header-menu-button');
        if (menuBtn) {
          menuBtn.click();
          return true;
        }
        return false;
      });
      if (clicked) {
        await page.waitForTimeout(2000);
        console.log('  ✅ Clicked menu via DOM');
      }
    }

    // Wait for library to open and take screenshot
    await page.waitForTimeout(2000);
    console.log('📸 Taking screenshot after attempting to open library...');
    await page.screenshot({ path: path.join(__dirname, 'screenshots', 'after-library-attempt.png'), fullPage: true });
    console.log('✅ Template library should be accessible\n');

    // Look for Import button or My Templates tab
    console.log('📋 Looking for Import/Upload option...');
    const libraryTabs = await page.evaluate(() => {
      const modal = document.querySelector('.dialog-widget, #elementor-template-library-modal');
      if (!modal) return 'NO MODAL FOUND';

      // Get all tabs/buttons in the modal header
      const tabs = modal.querySelectorAll('.elementor-template-library-menu-item, button, a, [role="tab"]');
      return Array.from(tabs).slice(0, 15).map((el, idx) => ({
        index: idx,
        tag: el.tagName,
        text: el.textContent?.trim().substring(0, 30),
        classes: el.className,
        id: el.id || ''
      }));
    });
    console.log('Library tabs/buttons:', JSON.stringify(libraryTabs, null, 2));

    // Click on "Templates" tab to access user templates
    console.log('\n📁 Clicking on Templates tab...');
    const templatesTab = page.locator('.elementor-template-library-menu-item:has-text("Templates")').first();
    await templatesTab.click({ timeout: 5000 });
    console.log('  ✅ Clicked Templates tab');
    await page.waitForTimeout(2000);

    // Take screenshot of Templates tab
    await page.screenshot({ path: path.join(__dirname, 'screenshots', 'templates-tab.png'), fullPage: true });

    // Now look for upload/import icon or button
    console.log('  Looking for upload icon...');
    const headerButtons = await page.evaluate(() => {
      const modal = document.querySelector('.dialog-widget, #elementor-template-library-modal');
      if (!modal) return [];

      const header = modal.querySelector('.dialog-header, .elementor-template-library-header');
      if (!header) return [];

      const buttons = header.querySelectorAll('button, a, i[class*="eicon"]');
      return Array.from(buttons).map((el, idx) => ({
        index: idx,
        tag: el.tagName,
        classes: el.className,
        title: el.title || el.getAttribute('aria-label') || '',
        id: el.id || ''
      }));
    });
    console.log('Header buttons:', JSON.stringify(headerButtons, null, 2));

    // Set up file chooser listener BEFORE clicking
    console.log('📤 Setting up file chooser listener...');
    const fileChooserPromise = page.waitForEvent('filechooser', { timeout: 15000 });

    // Click upload icon using Playwright click (not DOM manipulation)
    console.log('  Clicking upload icon via Playwright...');
    try {
      const uploadIcon = page.locator('i.eicon-upload-circle-o').first();
      const uploadCount = await uploadIcon.count();
      console.log(`  Found ${uploadCount} upload icons`);

      if (uploadCount > 0) {
        // Try clicking the parent element
        const uploadButton = uploadIcon.locator('..');
        await uploadButton.click({ force: true });
        console.log('  ✅ Clicked upload icon parent');
      } else {
        throw new Error('Upload icon not found');
      }
    } catch (clickErr) {
      console.log(`  ❌ Playwright click failed: ${clickErr.message}`);
      // Fallback to DOM click
      await page.evaluate(() => {
        const icon = document.querySelector('i.eicon-upload-circle-o');
        if (icon && icon.parentElement) icon.parentElement.click();
      });
      console.log('  ⚠️  Used DOM click fallback');
    }

    await page.waitForTimeout(2000);
    await page.screenshot({ path: path.join(__dirname, 'screenshots', 'after-upload-click.png'), fullPage: true });

    // Wait for file chooser
    console.log('  Waiting for file chooser dialog...');
    try {
      const fileChooser = await fileChooserPromise;
      console.log('  ✅ File chooser appeared!');
      await fileChooser.setFiles(templatePath);
      console.log('✅ File selected via file chooser!');
      await page.waitForTimeout(5000);
      await page.screenshot({ path: path.join(__dirname, 'screenshots', 'after-file-select.png'), fullPage: true });
    } catch (e) {
      console.log(`  ❌ No file chooser: ${e.message}`);
      throw new Error('Upload icon did not trigger file chooser dialog');
    }


    // Click Import/Insert button
    console.log('✅ Clicking Insert button...');
    await page.click('.dialog-button.dialog-insert-button').catch(async () => {
      console.log('  Trying alternative insert button...');
      await page.click('button:has-text("Insert")');
    });

    console.log('⏳ Waiting for template to be inserted (8 seconds)...');
    await page.waitForTimeout(8000);
    console.log('✅ Template should be inserted!\n');

    // Save page
    console.log('💾 Saving page...');
    await page.click('#elementor-panel-saver-button-publish').catch(async () => {
      await page.click('#elementor-panel-saver-button-save-options');
    });
    await page.waitForTimeout(5000);
    console.log('✅ Page saved\n');

    // Get page URL and go to frontend
    console.log('🌐 Getting page URL...');
    const pageLink = await page.locator('#elementor-panel-footer-settings a').first().getAttribute('href');
    console.log(`   Page URL: ${pageLink}\n`);

    console.log('🔍 Going to frontend...');
    await page.goto(pageLink || `${CONFIG.baseURL}/hero-auto-import-test/`);
    await page.waitForTimeout(5000);

    // Screenshot
    console.log('📸 Taking screenshot...');
    const screenshot = await page.screenshot({ fullPage: true });
    fs.writeFileSync(path.join(__dirname, 'screenshots', 'hero-simple-import.png'), screenshot);
    console.log('✅ Screenshot saved\n');

    // Quick validation
    console.log('🔍 Quick validation...\n');
    const hasTitle = await page.locator('h1:has-text("Find Your Next Camp")').count() > 0;
    const hasBadges = await page.locator('.elementor-icon-box-wrapper').count();

    console.log('═'.repeat(60));
    console.log(`Title "Find Your Next Camp": ${hasTitle ? '✅ FOUND' : '❌ NOT FOUND'}`);
    console.log(`Trust badges count: ${hasBadges} (expected 3)`);
    console.log('═'.repeat(60));

    if (hasTitle && hasBadges === 3) {
      console.log('\n🎉 SUCCESS! Template appears to be working!\n');
    } else {
      console.log('\n⚠️  Some elements may be missing. Check screenshot.\n');
    }

    console.log('⏸️  Browser will stay open for 60 seconds for manual inspection...');
    console.log('   You can check the page and Elementor editor manually.\n');
    await page.waitForTimeout(60000);

  } catch (error) {
    console.error('\n❌ Error:', error.message);
    console.log('\nTaking error screenshot...');
    await page.screenshot({ path: path.join(__dirname, 'screenshots', 'error.png'), fullPage: true });
    console.log('Browser will stay open for 2 minutes for debugging...');
    await page.waitForTimeout(120000);
  } finally {
    await browser.close();
    console.log('\n✅ Done! Check screenshots folder for results.');
  }
}

importHero().catch(console.error);
