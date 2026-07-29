/**
 * Compare Elementor template with static design side-by-side
 * Helps identify exact visual differences
 */

const { chromium } = require('playwright');
const path = require('path');

async function compareScreenshots() {
  console.log('🔍 Comparing static design vs Elementor template...\n');

  const browser = await chromium.launch({
    headless: false,
    slowMo: 300,
  });

  const context = await browser.newContext({
    viewport: { width: 1920, height: 1080 },
  });

  try {
    // Open static design
    console.log('📄 Opening static design...');
    const staticPage = await context.newPage();
    const staticPath = 'file://' + path.resolve(__dirname, '../ridemaster-design/index.html');
    await staticPage.goto(staticPath);
    await staticPage.waitForTimeout(2000);

    // Open Elementor page
    console.log('📄 Opening Elementor page...');
    const elementorPage = await context.newPage();
    await elementorPage.goto('https://staging4.ridemaster.eu/?p=369'); // Latest test page
    await elementorPage.waitForTimeout(3000);

    // Extract measurements from static
    console.log('\n📏 Measuring static design...');
    const staticMetrics = await staticPage.evaluate(() => {
      const hero = document.querySelector('.hero');
      const title = hero.querySelector('.hero__title');
      const subtitle = hero.querySelector('.hero__subtitle');
      const searchBar = hero.querySelector('.search-bar');
      const trustStrip = hero.querySelector('.trust-strip');

      return {
        hero: {
          height: hero.offsetHeight,
          padding: window.getComputedStyle(hero).padding,
        },
        title: {
          text: title.textContent.trim(),
          fontSize: window.getComputedStyle(title).fontSize,
          fontWeight: window.getComputedStyle(title).fontWeight,
          lineHeight: window.getComputedStyle(title).lineHeight,
          marginBottom: window.getComputedStyle(title).marginBottom,
        },
        subtitle: {
          text: subtitle.textContent.trim(),
          fontSize: window.getComputedStyle(subtitle).fontSize,
          lineHeight: window.getComputedStyle(subtitle).lineHeight,
          marginBottom: window.getComputedStyle(subtitle).marginBottom,
        },
        searchBar: {
          marginBottom: window.getComputedStyle(searchBar).marginBottom,
          maxWidth: window.getComputedStyle(searchBar).maxWidth,
        },
        trustStrip: {
          gap: window.getComputedStyle(trustStrip).gap,
          items: Array.from(trustStrip.querySelectorAll('.trust-strip__item')).map(item => ({
            text: item.textContent.trim(),
            fontSize: window.getComputedStyle(item).fontSize,
            gap: window.getComputedStyle(item).gap,
          })),
        },
      };
    });

    console.log('Static metrics:', JSON.stringify(staticMetrics, null, 2));

    // Extract measurements from Elementor
    console.log('\n📏 Measuring Elementor template...');
    const elementorMetrics = await elementorPage.evaluate(() => {
      const hero = document.querySelector('.elementor-section, .elementor-element');
      const title = document.querySelector('h1');
      const subtitle = document.querySelector('.elementor-text-editor p');

      return {
        hero: {
          height: hero ? hero.offsetHeight : 0,
          padding: hero ? window.getComputedStyle(hero).padding : '',
        },
        title: title ? {
          text: title.textContent.trim(),
          fontSize: window.getComputedStyle(title).fontSize,
          fontWeight: window.getComputedStyle(title).fontWeight,
          lineHeight: window.getComputedStyle(title).lineHeight,
          marginBottom: window.getComputedStyle(title).marginBottom,
        } : null,
        subtitle: subtitle ? {
          text: subtitle.textContent.trim(),
          fontSize: window.getComputedStyle(subtitle).fontSize,
          lineHeight: window.getComputedStyle(subtitle).lineHeight,
          marginBottom: window.getComputedStyle(subtitle).marginBottom,
        } : null,
      };
    });

    console.log('Elementor metrics:', JSON.stringify(elementorMetrics, null, 2));

    // Compare and highlight differences
    console.log('\n📊 DIFFERENCES:\n');
    console.log('═'.repeat(70));

    if (staticMetrics.title.fontSize !== elementorMetrics.title?.fontSize) {
      console.log(`❌ Title font-size: ${staticMetrics.title.fontSize} vs ${elementorMetrics.title?.fontSize}`);
    } else {
      console.log(`✅ Title font-size: ${staticMetrics.title.fontSize}`);
    }

    if (staticMetrics.title.marginBottom !== elementorMetrics.title?.marginBottom) {
      console.log(`❌ Title margin-bottom: ${staticMetrics.title.marginBottom} vs ${elementorMetrics.title?.marginBottom}`);
    } else {
      console.log(`✅ Title margin-bottom: ${staticMetrics.title.marginBottom}`);
    }

    if (staticMetrics.subtitle.fontSize !== elementorMetrics.subtitle?.fontSize) {
      console.log(`❌ Subtitle font-size: ${staticMetrics.subtitle.fontSize} vs ${elementorMetrics.subtitle?.fontSize}`);
    } else {
      console.log(`✅ Subtitle font-size: ${staticMetrics.subtitle.fontSize}`);
    }

    if (staticMetrics.subtitle.marginBottom !== elementorMetrics.subtitle?.marginBottom) {
      console.log(`❌ Subtitle margin-bottom: ${staticMetrics.subtitle.marginBottom} vs ${elementorMetrics.subtitle?.marginBottom}`);
    } else {
      console.log(`✅ Subtitle margin-bottom: ${staticMetrics.subtitle.marginBottom}`);
    }

    console.log('═'.repeat(70));

    console.log('\n⏸️  Both pages open for manual comparison...');
    console.log('Press Ctrl+C to close when done.');
    await new Promise(() => {}); // Keep open indefinitely

  } catch (error) {
    console.error('❌ Error:', error.message);
  } finally {
    await browser.close();
  }
}

compareScreenshots().catch(console.error);
