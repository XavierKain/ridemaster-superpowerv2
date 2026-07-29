/**
 * Extract ALL JetEngine meta fields by scrolling and inspecting the camp edit page
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

async function extractAllFields() {
  console.log('🔍 Extracting ALL meta fields from camp...\n');

  const browser = await chromium.launch({ headless: true });
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

    // Go to edit camp
    console.log('✏️  Opening camp for editing...');
    await page.goto(`${CONFIG.wpAdminURL}/edit.php?post_type=camp`);
    await page.waitForTimeout(2000);

    const firstCampLink = page.locator('.row-title').first();
    await firstCampLink.click();
    await page.waitForTimeout(3000);

    // Scroll multiple times to load all lazy content
    console.log('📜 Scrolling to load all content...');
    for (let i = 0; i < 5; i++) {
      await page.evaluate(() => window.scrollBy(0, 500));
      await page.waitForTimeout(500);
    }
    await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
    await page.waitForTimeout(2000);

    // Take full screenshot
    const screenshot = await page.screenshot({ fullPage: true });
    fs.writeFileSync(path.join(__dirname, 'screenshots', 'camp-edit-complete.png'), screenshot);
    console.log('  📸 Saved: camp-edit-complete.png\n');

    // Extract ALL fields from the Settings meta box
    console.log('🔎 Extracting meta fields...');
    const fieldsData = await page.evaluate(() => {
      const fields = [];

      // Look for JetEngine meta box
      const settingsBox = document.querySelector('#jet-engine-cpt-1, .jet-engine-meta-box');
      if (!settingsBox) {
        return { error: 'Settings meta box not found', fields: [] };
      }

      // Find all input, textarea, select elements with names starting with 'camp_'
      const inputs = settingsBox.querySelectorAll('input[name^="camp_"], textarea[name^="camp_"], select[name^="camp_"]');

      inputs.forEach(input => {
        const label = input.closest('tr, .jet-form-row, .components-base-control')?.querySelector('label, .jet-form-row__label, th');

        fields.push({
          name: input.name || '',
          type: input.type || input.tagName.toLowerCase(),
          value: input.value || '',
          label: label?.textContent?.trim() || '',
        });
      });

      // Also check for custom structure
      const allLabels = settingsBox.querySelectorAll('label');
      allLabels.forEach(label => {
        const labelText = label.textContent?.trim();
        const nameField = label.closest('tr, .jet-form-row')?.querySelector('.jet-engine-meta-field-name, small');
        if (nameField) {
          const fieldName = nameField.textContent?.replace('Name:', '').trim();
          if (fieldName && fieldName.startsWith('camp_')) {
            // Check if not already in fields
            if (!fields.find(f => f.name === fieldName)) {
              fields.push({
                name: fieldName,
                type: 'unknown',
                value: '',
                label: labelText,
              });
            }
          }
        }
      });

      return { fields };
    });

    console.log(`  Found ${fieldsData.fields.length} fields\n`);

    // Display fields
    console.log('📋 Meta Fields Found:');
    console.log('─'.repeat(80));
    fieldsData.fields.forEach(field => {
      console.log(`  ${field.label || '(no label)'}`);
      console.log(`    Name: ${field.name}`);
      console.log(`    Type: ${field.type}`);
      if (field.value) {
        const displayValue = field.value.length > 50 ? field.value.substring(0, 50) + '...' : field.value;
        console.log(`    Value: ${displayValue}`);
      }
      console.log('');
    });

    // Also check taxonomies
    console.log('\n🏷️  Checking taxonomies...');
    const taxonomies = await page.evaluate(() => {
      const taxBoxes = document.querySelectorAll('.categorydiv, .tagsdiv');
      return Array.from(taxBoxes).map(box => {
        const title = box.closest('.postbox')?.querySelector('.hndle, h2');
        return {
          title: title?.textContent?.trim() || '',
          id: box.id || '',
        };
      });
    });

    console.log(`  Found ${taxonomies.length} taxonomy boxes`);
    taxonomies.forEach(tax => {
      console.log(`    - ${tax.title} (${tax.id})`);
    });

    // Save complete data
    const completeData = {
      timestamp: new Date().toISOString(),
      metaFields: fieldsData.fields,
      taxonomies: taxonomies,
    };

    fs.writeFileSync(
      path.join(__dirname, 'complete-fields-structure.json'),
      JSON.stringify(completeData, null, 2)
    );
    console.log('\n💾 Complete data saved to: complete-fields-structure.json');

    console.log('\n✅ Extraction complete!');

  } catch (error) {
    console.error('❌ Error:', error);
    throw error;
  } finally {
    await browser.close();
  }
}

// Create screenshots directory
if (!fs.existsSync(path.join(__dirname, 'screenshots'))) {
  fs.mkdirSync(path.join(__dirname, 'screenshots'));
}

extractAllFields().catch(console.error);
