/**
 * Import Elementor template via WordPress REST API
 * Bypasses UI automation issues
 */

const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const CONFIG = {
  loginURL: 'https://staging4.ridemaster.eu/wp-login.php',
  wpAdminURL: 'https://staging4.ridemaster.eu/wp-admin',
  baseURL: 'https://staging4.ridemaster.eu',
  restAPI: 'https://staging4.ridemaster.eu/wp-json',
  username: 'xavierkain.consulting@gmail.com',
  password: '8Bc99WVWc4!zmN@fqdd!',
};

async function importViaAPI() {
  console.log('🚀 Importing Hero Template via REST API...\n');

  const browser = await chromium.launch({
    headless: false,
    slowMo: 500,
  });

  const context = await browser.newContext({
    viewport: { width: 1920, height: 1400 },  // Taller viewport to see footer buttons
  });
  const page = await context.newPage();

  try {
    const templatePath = path.join(__dirname, 'generated-templates', 'hero-auto-v10-with-globals.json');
    const templateData = JSON.parse(fs.readFileSync(templatePath, 'utf8'));
    console.log(`📄 Template loaded: ${templatePath}\n`);

    // Step 1: Login to get authentication cookies
    console.log('📝 Logging in...');
    await page.goto(CONFIG.loginURL);
    await page.waitForSelector('input[name="log"]');
    await page.fill('input[name="log"]', CONFIG.username);
    await page.fill('input[name="pwd"]', CONFIG.password);
    await page.click('input[name="wp-submit"]');
    await page.waitForTimeout(5000);
    console.log('✅ Logged in\n');

    // Step 2: Get nonce from admin page
    console.log('🔑 Getting REST API nonce...');
    await page.goto(`${CONFIG.wpAdminURL}/edit.php?post_type=elementor_library`);
    await page.waitForTimeout(3000);

    const nonceData = await page.evaluate(() => {
      const nonces = {};

      // Try to get nonce from wpApiSettings
      if (window.wpApiSettings && window.wpApiSettings.nonce) {
        nonces.wpApiNonce = window.wpApiSettings.nonce;
      }

      // Try elementorCommon
      if (window.elementorCommon && window.elementorCommon.config) {
        nonces.elementorNonce = window.elementorCommon.config.nonce || null;
      }

      // Try _wpRestNonce
      if (window._wpRestNonce) {
        nonces.wpRestNonce = window._wpRestNonce;
      }

      return nonces;
    });

    console.log('  Available nonces:', Object.keys(nonceData));
    const nonce = nonceData.wpApiNonce || nonceData.wpRestNonce || nonceData.elementorNonce;
    console.log(`  Using nonce: ${nonce ? 'Yes' : 'No'}`);
    console.log('');

    if (!nonce) {
      throw new Error('Could not find REST API nonce');
    }

    // Step 3: Create template via REST API
    console.log('📤 Creating template via REST API...');

    const apiResponse = await page.evaluate(async (args) => {
      try {
        const response = await fetch(`${args.restAPI}/wp/v2/elementor_library`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': args.nonce,
          },
          credentials: 'same-origin',
          body: JSON.stringify({
            title: 'Homepage Hero - v10 With Globals',
            status: 'publish',
            type: 'elementor_library',
            meta: {
              _elementor_data: JSON.stringify(args.templateData.content),
              _elementor_page_settings: args.templateData.page_settings || {},
              _elementor_template_type: args.templateData.type || 'page',
            }
          }),
        });

        const data = await response.json();
        return {
          ok: response.ok,
          status: response.status,
          data: data,
        };
      } catch (error) {
        return {
          ok: false,
          error: error.message,
        };
      }
    }, { restAPI: CONFIG.restAPI, templateData, nonce });

    console.log('API Response:', JSON.stringify(apiResponse, null, 2));

    if (apiResponse.ok && apiResponse.data.id) {
      console.log('✅ Template created successfully!\n');
      console.log(`   Template ID: ${apiResponse.data.id}`);
      console.log(`   Template URL: ${apiResponse.data.link}\n`);

      // Step 4: Create new test page
      console.log(`📄 Creating new test page...`);
      await page.goto(`${CONFIG.wpAdminURL}/post-new.php?post_type=page`);
      await page.waitForTimeout(5000);

      // Set page title (WordPress block editor)
      const titleSelector = '.editor-post-title__input, #post-title-0, input[name="post_title"]';
      await page.fill(titleSelector, 'Hero Template Test v10 Globals').catch(async () => {
        // Fallback: try typing if fill doesn't work
        await page.click(titleSelector);
        await page.keyboard.type('Hero Template Test v10 Globals');
      });
      await page.waitForTimeout(1000);

      // Open Elementor
      console.log('🎨 Opening Elementor...');
      await page.click('.elementor-switch-mode').catch(async () => {
        await page.click('a.elementor-create-new-post, #elementor-switch-mode-button');
      });
      await page.waitForTimeout(15000);

      // Open template library and insert the template
      console.log('📚 Opening template library...');
      await page.keyboard.press('Meta+Shift+L');
      await page.waitForTimeout(3000);

      // Go to Templates tab
      const templatesTab = page.locator('.elementor-template-library-menu-item:has-text("Templates")').first();
      await templatesTab.click({ timeout: 5000 });
      await page.waitForTimeout(2000);

      // Take screenshot
      await page.screenshot({ path: path.join(__dirname, 'screenshots', 'api-import-library.png'), fullPage: true });

      // Look for our newly created template
      console.log('🔍 Looking for newly created template...');
      const templateFound = await page.evaluate(async (templateTitle) => {
        // Look for template in the list
        const templates = document.querySelectorAll('.elementor-template-library-template');
        for (const template of templates) {
          const titleEl = template.querySelector('.elementor-template-library-template-name');
          if (titleEl && titleEl.textContent.includes('Homepage Hero - v10 With Globals')) {
            const insertBtn = template.querySelector('.elementor-template-library-template-insert');
            if (insertBtn) {
              insertBtn.click();
              return true;
            }
          }
        }
        return false;
      }, 'Homepage Hero - v10 With Globals');

      if (templateFound) {
        console.log('✅ Template inserted!\n');
        await page.waitForTimeout(8000);

        // Save page using DOM click (footer buttons may be hidden)
        console.log('💾 Saving page...');
        const saved = await page.evaluate(() => {
          // Try Publish button first
          const publishBtn = document.querySelector('#elementor-panel-saver-button-publish');
          if (publishBtn) {
            publishBtn.click();
            return 'publish';
          }
          // Fallback to Save Draft
          const saveBtn = document.querySelector('#elementor-panel-footer-sub-menu-item-save-draft');
          if (saveBtn) {
            saveBtn.click();
            return 'draft';
          }
          return null;
        });

        if (saved) {
          console.log(`  ✅ Clicked ${saved} button via DOM`);
          await page.waitForTimeout(6000);  // Wait for save to complete
        } else {
          console.log('  ⚠️  Could not find save button, trying visible click...');
          await page.click('#elementor-panel-saver-button-publish').catch(() => {
            console.log('  ❌ Publish button not clickable');
          });
          await page.waitForTimeout(3000);
        }

        // Navigate to frontend
        console.log('📸 Taking frontend screenshot...');
        const currentUrl = page.url();
        const postId = currentUrl.match(/post=(\d+)/);
        let frontendUrl = `${CONFIG.baseURL}/hero-template-test-api-import/`;

        if (postId) {
          frontendUrl = `${CONFIG.baseURL}/?p=${postId[1]}`;
        }

        await page.goto(frontendUrl);
        await page.waitForTimeout(5000);

        const screenshot = await page.screenshot({ fullPage: true });
        fs.writeFileSync(path.join(__dirname, 'screenshots', 'hero-v10-globals-frontend.png'), screenshot);

        console.log('✅ Screenshot saved\n');

        // Quick validation
        console.log('🔍 Validating template...\n');
        const hasTitle = await page.locator('h1:has-text("Find Your Next Camp")').count() > 0;
        const hasBadges = await page.locator('.elementor-icon-box-wrapper').count();

        console.log('═'.repeat(60));
        console.log(`Title "Find Your Next Camp": ${hasTitle ? '✅ FOUND' : '❌ NOT FOUND'}`);
        console.log(`Trust badges count: ${hasBadges} (expected 3)`);
        console.log('═'.repeat(60));

        if (hasTitle && hasBadges === 3) {
          console.log('\n🎉 SUCCESS! Template imported and rendered correctly via API!\n');
        } else {
          console.log('\n⚠️  Template imported but some elements may be missing.\n');
        }

      } else {
        console.log('⚠️  Template created but could not auto-insert. Check library manually.\n');
      }

    } else {
      console.log('❌ API import failed\n');
      console.log('Error:', apiResponse.error || (apiResponse.data && apiResponse.data.message));

      // Try alternative: Direct database import simulation
      console.log('\n📝 Attempting alternative method...');
      console.log('   You can manually import the template from the Elementor library.');
    }

    console.log('\n⏸️  Browser will stay open for 60 seconds...');
    await page.waitForTimeout(60000);

  } catch (error) {
    console.error('\n❌ Error:', error.message);
    console.log('\nTaking error screenshot...');
    await page.screenshot({ path: path.join(__dirname, 'screenshots', 'api-error.png'), fullPage: true });
    await page.waitForTimeout(120000);
  } finally {
    await browser.close();
    console.log('\n✅ Done!');
  }
}

importViaAPI().catch(console.error);
