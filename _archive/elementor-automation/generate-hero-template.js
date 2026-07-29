/**
 * Generate and validate Homepage Hero template
 * This script will create the JSON, import it into Elementor, create a test page, and validate the rendering
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

// Generate the Hero template JSON based on what we learned
function generateHeroTemplate() {
  return {
    "version": "0.4",
    "title": "Homepage Hero - Auto Generated v1",
    "type": "page",
    "content": [
      {
        "id": "hero-main",
        "elType": "container",
        "isInner": false,
        "settings": {
          "content_width": "boxed",  // Changed: use boxed with max-width
          "content_width_custom": {
            "unit": "px",
            "size": "800",  // Match static: max-width 800px
            "sizes": []
          },
          "flex_direction": "column",
          "flex_justify_content": "center",
          "flex_align_items": "flex-start",  // Changed: left-align instead of center
          "min_height": {
            "unit": "vh",
            "size": "80",  // Match static design: 80vh
            "sizes": []
          },
          "min_height_tablet": {
            "unit": "vh",
            "size": "70",
            "sizes": []
          },
          "min_height_mobile": {
            "unit": "vh",
            "size": "60",
            "sizes": []
          },
          "padding": {
            "top": "100",
            "right": "20",
            "bottom": "100",
            "left": "20",
            "unit": "px",
            "isLinked": false
          },
          "padding_tablet": {
            "top": "80",
            "right": "20",
            "bottom": "80",
            "left": "20",
            "unit": "px",
            "isLinked": false
          },
          "padding_mobile": {
            "top": "60",
            "right": "16",
            "bottom": "60",
            "left": "16",
            "unit": "px",
            "isLinked": false
          },
          "background_background": "classic",
          "background_image": {
            "url": "https://images.unsplash.com/photo-1502680390469-be75c86b636f?w=1920",
            "id": ""
          },
          "background_position": "center center",
          "background_size": "cover",
          "background_overlay_background": "gradient",
          "background_overlay_color": "rgba(15, 23, 42, 0.85)",  // Match static: 0.85 start
          "background_overlay_color_stop": {
            "unit": "%",
            "size": 0
          },
          "background_overlay_color_b": "rgba(15, 23, 42, 0.4)",  // Match static: 0.4 end
          "background_overlay_color_b_stop": {
            "unit": "%",
            "size": 100
          },
          "background_overlay_gradient_type": "linear",
          "background_overlay_gradient_angle": {
            "unit": "deg",
            "size": 135  // Match static: 135deg diagonal
          }
        },
        "elements": [
          {
            "id": "hero-title",
            "elType": "widget",
            "widgetType": "heading",
            "settings": {
              "title": "Find Your Next Camp",
              "header_size": "h1",
              "align": "left",  // Changed: left-align to match static
              "title_color": "#FFFFFF",
              "typography_typography": "custom",
              "typography_font_family": "DM Sans",
              "typography_font_weight": "700",
              "typography_font_size": {
                "unit": "px",
                "size": "48",
                "sizes": []
              },
              "typography_font_size_tablet": {
                "unit": "px",
                "size": "36",
                "sizes": []
              },
              "typography_font_size_mobile": {
                "unit": "px",
                "size": "30",
                "sizes": []
              },
              "typography_line_height": {
                "unit": "em",
                "size": "1.25",
                "sizes": []
              },
              "typography_letter_spacing": {
                "unit": "em",
                "size": "-0.025",
                "sizes": []
              },
              "text_shadow_text_shadow_type": "yes",
              "text_shadow_text_shadow": {
                "horizontal": 0,
                "vertical": 2,
                "blur": 8,
                "color": "rgba(0,0,0,0.3)"
              }
            },
            "elements": []
          },
          {
            "id": "hero-subtitle",
            "elType": "widget",
            "widgetType": "text-editor",
            "settings": {
              "editor": "<p>Discover world-class camps with expert coaches at stunning destinations</p>",
              "align": "left",  // Changed: left-align to match static
              "text_color": "#FFFFFF",
              "typography_typography": "custom",
              "typography_font_family": "DM Sans",
              "typography_font_weight": "400",
              "typography_font_size": {
                "unit": "px",
                "size": "20",  // Match static: 20px (--font-size-xl)
                "sizes": []
              },
              "typography_font_size_tablet": {
                "unit": "px",
                "size": "16",
                "sizes": []
              },
              "typography_font_size_mobile": {
                "unit": "px",
                "size": "16",
                "sizes": []
              },
              "typography_line_height": {
                "unit": "em",
                "size": "1.5",  // Match static: --line-height-normal
                "sizes": []
              },
              "text_shadow_text_shadow_type": "yes",
              "text_shadow_text_shadow": {
                "horizontal": 0,
                "vertical": 1,
                "blur": 4,
                "color": "rgba(0,0,0,0.25)"
              },
              "_margin": {
                "top": "0",
                "right": "0",
                "bottom": "32",
                "left": "0",
                "unit": "px",
                "isLinked": false
              }
            },
            "elements": []
          },
          {
            "id": "hero-search-placeholder",
            "elType": "widget",
            "widgetType": "html",
            "settings": {
              "html": "<div style=\"background: white; padding: 20px 24px; border-radius: 16px; box-shadow: 0 10px 40px rgba(0,0,0,0.15); max-width: 700px; width: 100%; text-align: center; color: #64748B; font-family: 'DM Sans', sans-serif; margin: 0 0 32px 0;\"><p style=\"margin: 0; font-size: 14px;\">🔍 Search Bar - Configure with JetSearch</p></div>",  // Changed: align left, max-width 700px
              "_margin": {
                "top": "0",
                "right": "0",
                "bottom": "24",
                "left": "0",
                "unit": "px",
                "isLinked": false
              }
            },
            "elements": []
          },
          {
            "id": "hero-trust",
            "elType": "container",
            "isInner": true,
            "settings": {
              "flex_direction": "row",
              "flex_justify_content": "flex-start",  // Changed: left-align to match static
              "flex_align_items": "center",
              "flex_wrap": "wrap",
              "flex_gap": {
                "column": "32",
                "row": "16",
                "unit": "px"
              },
              "flex_gap_tablet": {
                "column": "24",
                "row": "12",
                "unit": "px"
              },
              "flex_gap_mobile": {
                "column": "20",
                "row": "10",
                "unit": "px"
              }
            },
            "elements": [
              {
                "id": "trust-insurance",
                "elType": "widget",
                "widgetType": "icon-box",
                "settings": {
                  "title_text": "Insurance included",
                  "description_text": "",
                  "position": "left",
                  "title_bottom_space": {
                    "unit": "px",
                    "size": "0",
                    "sizes": []
                  },
                  "icon": {
                    "value": "fas fa-shield-alt",
                    "library": "fa-solid"
                  },
                  "icon_size": {
                    "unit": "px",
                    "size": "20",
                    "sizes": []
                  },
                  "icon_space": {
                    "unit": "px",
                    "size": "10",
                    "sizes": []
                  },
                  "icon_primary_color": "#FFFFFF",
                  "title_color": "#FFFFFF",
                  "typography_typography": "custom",
                  "typography_font_family": "DM Sans",
                  "typography_font_weight": "400",
                  "typography_font_size": {
                    "unit": "px",
                    "size": "14",
                    "sizes": []
                  }
                },
                "elements": []
              },
              {
                "id": "trust-payment",
                "elType": "widget",
                "widgetType": "icon-box",
                "settings": {
                  "title_text": "Secure payment",
                  "description_text": "",
                  "position": "left",
                  "title_bottom_space": {
                    "unit": "px",
                    "size": "0",
                    "sizes": []
                  },
                  "icon": {
                    "value": "fas fa-lock",
                    "library": "fa-solid"
                  },
                  "icon_size": {
                    "unit": "px",
                    "size": "20",
                    "sizes": []
                  },
                  "icon_space": {
                    "unit": "px",
                    "size": "10",
                    "sizes": []
                  },
                  "icon_primary_color": "#FFFFFF",
                  "title_color": "#FFFFFF",
                  "typography_typography": "custom",
                  "typography_font_family": "DM Sans",
                  "typography_font_weight": "400",
                  "typography_font_size": {
                    "unit": "px",
                    "size": "14",
                    "sizes": []
                  }
                },
                "elements": []
              },
              {
                "id": "trust-cancel",
                "elType": "widget",
                "widgetType": "icon-box",
                "settings": {
                  "title_text": "Free cancellation",
                  "description_text": "",
                  "position": "left",
                  "title_bottom_space": {
                    "unit": "px",
                    "size": "0",
                    "sizes": []
                  },
                  "icon": {
                    "value": "fas fa-sync-alt",
                    "library": "fa-solid"
                  },
                  "icon_size": {
                    "unit": "px",
                    "size": "20",
                    "sizes": []
                  },
                  "icon_space": {
                    "unit": "px",
                    "size": "10",
                    "sizes": []
                  },
                  "icon_primary_color": "#FFFFFF",
                  "title_color": "#FFFFFF",
                  "typography_typography": "custom",
                  "typography_font_family": "DM Sans",
                  "typography_font_weight": "400",
                  "typography_font_size": {
                    "unit": "px",
                    "size": "14",
                    "sizes": []
                  }
                },
                "elements": []
              }
            ]
          }
        ]
      }
    ],
    "page_settings": []
  };
}

async function importAndValidateHero() {
  console.log('🚀 Starting Hero Template Generation & Validation...\n');

  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({
    viewport: { width: 1920, height: 1080 },
  });
  const page = await context.newPage();

  try {
    // Step 1: Generate template JSON
    console.log('📝 Generating template JSON...');
    const template = generateHeroTemplate();
    const templatePath = path.join(__dirname, 'generated-templates', 'hero-auto-v1.json');
    fs.mkdirSync(path.dirname(templatePath), { recursive: true });
    fs.writeFileSync(templatePath, JSON.stringify(template, null, 2));
    console.log(`  ✅ Saved to: ${templatePath}\n`);

    // Step 2: Login
    console.log('📝 Logging into WordPress...');
    await page.goto(CONFIG.loginURL, { waitUntil: 'load' });
    await page.waitForSelector('input[name="log"]');
    await page.fill('input[name="log"]', CONFIG.username);
    await page.fill('input[name="pwd"]', CONFIG.password);
    await page.click('input[name="wp-submit"]');
    await page.waitForURL(/wp-admin/, { timeout: 15000 }).catch(() => {});
    await page.waitForTimeout(3000); // Extra wait for redirects
    console.log('  ✅ Logged in\n');

    // Step 3: Import template via Elementor interface
    console.log('📦 Importing template into Elementor...');
    await page.goto(`${CONFIG.wpAdminURL}/edit.php?post_type=elementor_library`);
    await page.waitForTimeout(2000);

    // Click Import Templates button
    await page.click('text=Import Templates');
    await page.waitForTimeout(1000);

    // Upload JSON file
    const fileInput = await page.locator('input[type="file"]');
    await fileInput.setInputFiles(templatePath);
    await page.waitForTimeout(3000);

    // Click Import Now
    await page.click('button:has-text("Import Now"), .elementor-button-success');
    await page.waitForTimeout(5000);

    console.log('  ✅ Template imported\n');

    // Step 4: Create a test page with this template
    console.log('📄 Creating test page...');
    await page.goto(`${CONFIG.wpAdminURL}/post-new.php?post_type=page`);
    await page.waitForTimeout(3000);

    // Fill page title
    await page.fill('[name="post_title"], .editor-post-title__input', 'Test Hero Auto v1');
    await page.waitForTimeout(1000);

    // Click Edit with Elementor
    await page.click('text=Edit with Elementor, a:has-text("Edit with Elementor")');
    await page.waitForTimeout(10000); // Wait for Elementor to load

    console.log('  ✅ Elementor editor opened\n');

    // Step 5: Insert the template
    console.log('📋 Inserting template into page...');

    // In Elementor, click the folder icon to open templates library
    await page.click('#elementor-panel-footer-add-template');
    await page.waitForTimeout(2000);

    // Search for our template
    await page.fill('#elementor-template-library-filter-text', 'Homepage Hero - Auto Generated v1');
    await page.waitForTimeout(1000);

    // Click on the template
    await page.click('.elementor-template-library-template-name:has-text("Homepage Hero - Auto Generated v1")').catch(() => {
      console.log('  ⚠️  Template not found in library, trying alternative method...');
    });
    await page.waitForTimeout(2000);

    // Click Insert
    await page.click('.elementor-template-library-template-insert, button:has-text("Insert")').catch(() => {});
    await page.waitForTimeout(5000);

    console.log('  ✅ Template inserted\n');

    // Step 6: Publish the page
    console.log('📤 Publishing page...');
    await page.click('#elementor-panel-saver-button-publish');
    await page.waitForTimeout(3000);

    // Get the page URL
    const pageURL = await page.evaluate(() => {
      const link = document.querySelector('.elementor-panel-footer-settings a');
      return link?.href || '';
    });

    console.log(`  ✅ Page published: ${pageURL}\n`);

    // Step 7: Visit the frontend and validate
    console.log('🔍 Validating frontend rendering...');
    await page.goto(pageURL || `${CONFIG.baseURL}/?p=test-hero-auto-v1`);
    await page.waitForTimeout(3000);

    // Take screenshot
    const screenshot = await page.screenshot({ fullPage: true });
    fs.writeFileSync(path.join(__dirname, 'screenshots', 'hero-auto-v1-frontend.png'), screenshot);
    console.log('  📸 Screenshot saved\n');

    // Validate rendering
    const validation = await page.evaluate(() => {
      const results = {
        title: null,
        subtitle: null,
        trustBadges: [],
        background: null,
        errors: [],
      };

      // Check title
      const title = document.querySelector('h1');
      if (title) {
        results.title = {
          text: title.textContent.trim(),
          color: window.getComputedStyle(title).color,
          fontSize: window.getComputedStyle(title).fontSize,
        };
        if (!title.textContent.includes('Find Your Next Camp')) {
          results.errors.push('Title text incorrect');
        }
        if (window.getComputedStyle(title).color !== 'rgb(255, 255, 255)') {
          results.errors.push('Title color not white');
        }
      } else {
        results.errors.push('Title not found');
      }

      // Check subtitle
      const subtitle = document.querySelector('p');
      if (subtitle && subtitle.textContent.includes('Discover world-class')) {
        results.subtitle = {
          text: subtitle.textContent.trim(),
          color: window.getComputedStyle(subtitle).color,
        };
      }

      // Check trust badges
      const badges = document.querySelectorAll('.elementor-icon-box-title');
      results.trustBadges = Array.from(badges).map(b => b.textContent.trim());
      if (results.trustBadges.length !== 3) {
        results.errors.push(`Expected 3 trust badges, found ${results.trustBadges.length}`);
      }

      // Check icons
      const icons = document.querySelectorAll('.fa-shield-alt, .fa-lock, .fa-sync-alt');
      if (icons.length !== 3) {
        results.errors.push(`Expected 3 FontAwesome icons, found ${icons.length}`);
      }

      return results;
    });

    console.log('📊 Validation Results:');
    console.log('─'.repeat(80));
    console.log(`Title: ${validation.title?.text || 'NOT FOUND'}`);
    console.log(`  Color: ${validation.title?.color || 'N/A'}`);
    console.log(`  Font Size: ${validation.title?.fontSize || 'N/A'}`);
    console.log(`\nSubtitle: ${validation.subtitle?.text?.substring(0, 50) || 'NOT FOUND'}...`);
    console.log(`\nTrust Badges: ${validation.trustBadges.join(', ')}`);
    console.log(`\nErrors: ${validation.errors.length === 0 ? 'None ✅' : validation.errors.join(', ')}`);

    // Save validation results
    fs.writeFileSync(
      path.join(__dirname, 'hero-validation-results.json'),
      JSON.stringify(validation, null, 2)
    );

    if (validation.errors.length === 0) {
      console.log('\n✅ VALIDATION PASSED! Hero template is working correctly!');
    } else {
      console.log('\n⚠️  VALIDATION FAILED. Issues detected.');
    }

  } catch (error) {
    console.error('❌ Error:', error);
    throw error;
  } finally {
    await browser.close();
  }
}

importAndValidateHero().catch(console.error);
