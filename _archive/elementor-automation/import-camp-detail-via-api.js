/**
 * Import Camp Detail Single template via WordPress REST API
 * Based on the successful Hero and Camp Card imports
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

async function importCampDetailViaAPI() {
  console.log('🚀 Importing Camp Detail Single template via REST API...\n');

  const browser = await chromium.launch({
    headless: false,
    slowMo: 500,
  });

  const context = await browser.newContext({
    viewport: { width: 1920, height: 1400 },
  });
  const page = await context.newPage();

  try {
    const templatePath = path.join(__dirname, 'generated-templates', 'camp-detail-single-v1.json');
    const templateData = JSON.parse(fs.readFileSync(templatePath, 'utf8'));
    console.log(`📄 Template loaded: ${templatePath}\n`);

    // Step 1: Login
    console.log('📝 Logging in...');
    await page.goto(CONFIG.loginURL);
    await page.waitForSelector('input[name="log"]');
    await page.fill('input[name="log"]', CONFIG.username);
    await page.fill('input[name="pwd"]', CONFIG.password);
    await page.click('input[name="wp-submit"]');
    await page.waitForTimeout(5000);
    console.log('✅ Logged in\n');

    // Step 2: Get nonce
    console.log('🔑 Getting REST API nonce...');
    await page.goto(`${CONFIG.wpAdminURL}/edit.php?post_type=elementor_library`);
    await page.waitForTimeout(3000);

    const nonceData = await page.evaluate(() => {
      const nonces = {};
      if (window.wpApiSettings && window.wpApiSettings.nonce) {
        nonces.wpApiNonce = window.wpApiSettings.nonce;
      }
      if (window.elementorCommon && window.elementorCommon.config) {
        nonces.elementorNonce = window.elementorCommon.config.nonce || null;
      }
      if (window._wpRestNonce) {
        nonces.wpRestNonce = window._wpRestNonce;
      }
      return nonces;
    });

    const nonce = nonceData.wpApiNonce || nonceData.wpRestNonce || nonceData.elementorNonce;
    console.log(`  Using nonce: ${nonce ? 'Yes' : 'No'}\n`);

    if (!nonce) {
      throw new Error('Could not find REST API nonce');
    }

    // Step 3: Create template via REST API
    console.log('📤 Creating Single template via REST API...');

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
            title: 'Camp Detail - Single v1',
            status: 'publish',
            type: 'elementor_library',
            meta: {
              _elementor_data: JSON.stringify(args.templateData.content),
              _elementor_page_settings: args.templateData.page_settings || {},
              _elementor_template_type: args.templateData.type || 'single',
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
      console.log('✅ Single template created successfully!\n');
      console.log(`   Template ID: ${apiResponse.data.id}`);
      console.log(`   Template URL: ${apiResponse.data.link}\n`);

      // Step 4: Navigate to Elementor library to verify
      console.log('📚 Verifying template in Elementor library...');
      await page.goto(`${CONFIG.wpAdminURL}/edit.php?post_type=elementor_library&tabs_group=library&elementor_library_type=single`);
      await page.waitForTimeout(3000);

      // Take screenshot
      await page.screenshot({ path: path.join(__dirname, 'screenshots', 'camp-detail-library.png'), fullPage: true });
      console.log('📸 Screenshot saved: camp-detail-library.png\n');

      // Check if template appears in list
      const templateVisible = await page.evaluate((templateTitle) => {
        const rows = document.querySelectorAll('tr.type-elementor_library');
        for (const row of rows) {
          const titleEl = row.querySelector('.row-title');
          if (titleEl && titleEl.textContent.includes(templateTitle)) {
            return true;
          }
        }
        return false;
      }, 'Camp Detail - Single v1');

      if (templateVisible) {
        console.log('✅ Template appears in Elementor library!\n');
      } else {
        console.log('⚠️  Template created but not visible in library list yet (may need page refresh)\n');
      }

      // Step 5: Preview the template on a test page
      console.log('📝 Creating test page to preview the detail template...');
      await page.goto(`${CONFIG.wpAdminURL}/post-new.php?post_type=page`);
      await page.waitForTimeout(5000);

      // Set page title
      const titleSelector = '.editor-post-title__input, #post-title-0, input[name="post_title"]';
      await page.fill(titleSelector, 'Camp Detail Test v1').catch(async () => {
        await page.click(titleSelector);
        await page.keyboard.type('Camp Detail Test v1');
      });
      await page.waitForTimeout(1000);

      // Open Elementor
      console.log('🎨 Opening Elementor...');
      await page.click('.elementor-switch-mode').catch(async () => {
        await page.click('a.elementor-create-new-post, #elementor-switch-mode-button');
      });
      await page.waitForTimeout(15000);

      // Open template library
      console.log('📚 Opening template library...');
      await page.keyboard.press('Meta+Shift+L');
      await page.waitForTimeout(3000);

      // Go to Templates tab
      const templatesTab = page.locator('.elementor-template-library-menu-item:has-text("Templates")').first();
      await templatesTab.click({ timeout: 5000 });
      await page.waitForTimeout(2000);

      // Take screenshot
      await page.screenshot({ path: path.join(__dirname, 'screenshots', 'camp-detail-template-library.png'), fullPage: true });
      console.log('📸 Screenshot saved: camp-detail-template-library.png\n');

      // Try to insert template
      console.log('🔍 Looking for newly created template...');
      const templateFound = await page.evaluate(async (templateTitle) => {
        const templates = document.querySelectorAll('.elementor-template-library-template');
        for (const template of templates) {
          const titleEl = template.querySelector('.elementor-template-library-template-name');
          if (titleEl && titleEl.textContent.includes('Camp Detail - Single v1')) {
            const insertBtn = template.querySelector('.elementor-template-library-template-insert');
            if (insertBtn) {
              insertBtn.click();
              return true;
            }
          }
        }
        return false;
      }, 'Camp Detail - Single v1');

      if (templateFound) {
        console.log('✅ Template inserted!\n');
        await page.waitForTimeout(8000);

        // Save page
        console.log('💾 Saving page...');
        const saved = await page.evaluate(() => {
          const publishBtn = document.querySelector('#elementor-panel-saver-button-publish');
          if (publishBtn) {
            publishBtn.click();
            return 'publish';
          }
          const saveBtn = document.querySelector('#elementor-panel-footer-sub-menu-item-save-draft');
          if (saveBtn) {
            saveBtn.click();
            return 'draft';
          }
          return null;
        });

        if (saved) {
          console.log(`  ✅ Clicked ${saved} button via DOM`);
          await page.waitForTimeout(6000);
        }

        // Navigate to frontend
        console.log('📸 Taking frontend screenshot...');
        const currentUrl = page.url();
        const postId = currentUrl.match(/post=(\d+)/);
        let frontendUrl = `${CONFIG.baseURL}/camp-detail-test-v1/`;

        if (postId) {
          frontendUrl = `${CONFIG.baseURL}/?p=${postId[1]}`;
        }

        await page.goto(frontendUrl);
        await page.waitForTimeout(5000);

        const screenshot = await page.screenshot({ fullPage: true });
        fs.writeFileSync(path.join(__dirname, 'screenshots', 'camp-detail-v1-frontend.png'), screenshot);

        console.log('✅ Screenshot saved: camp-detail-v1-frontend.png\n');

        // Validation
        console.log('🔍 Validating template...\n');
        const hasTitle = await page.locator('h1:has-text("Tarifa")').count() > 0;
        const hasBreadcrumb = await page.locator(':text("Home")').count() > 0;
        const hasCoach = await page.locator(':text("Marco Silva")').count() > 0;
        const hasBookingCard = await page.locator(':text("Reserve Your Spot")').count() > 0;
        const hasPrice = await page.locator(':text("€890")').count() > 0;

        console.log('═'.repeat(60));
        console.log(`Camp title: ${hasTitle ? '✅ FOUND' : '❌ NOT FOUND'}`);
        console.log(`Breadcrumb: ${hasBreadcrumb ? '✅ FOUND' : '❌ NOT FOUND'}`);
        console.log(`Coach section: ${hasCoach ? '✅ FOUND' : '❌ NOT FOUND'}`);
        console.log(`Booking card: ${hasBookingCard ? '✅ FOUND' : '❌ NOT FOUND'}`);
        console.log(`Price: ${hasPrice ? '✅ FOUND' : '❌ NOT FOUND'}`);
        console.log('═'.repeat(60));

        if (hasTitle && hasBreadcrumb && hasCoach && hasBookingCard && hasPrice) {
          console.log('\n🎉 SUCCESS! Camp Detail template imported and rendered correctly!\n');
        } else {
          console.log('\n⚠️  Template imported but some elements may be missing.\n');
        }

      } else {
        console.log('⚠️  Template created but could not auto-insert from library.\n');
        console.log('   The template ID is: ' + apiResponse.data.id);
        console.log('   You can manually apply it in Theme Builder settings.\n');
      }

    } else {
      console.log('❌ API import failed\n');
      console.log('Error:', apiResponse.error || (apiResponse.data && apiResponse.data.message));
    }

    console.log('\n⏸️  Browser will stay open for 60 seconds...');
    await page.waitForTimeout(60000);

  } catch (error) {
    console.error('\n❌ Error:', error.message);
    console.log('\nTaking error screenshot...');
    await page.screenshot({ path: path.join(__dirname, 'screenshots', 'camp-detail-error.png'), fullPage: true });
    await page.waitForTimeout(120000);
  } finally {
    await browser.close();
    console.log('\n✅ Done!');
  }
}

importCampDetailViaAPI().catch(console.error);
