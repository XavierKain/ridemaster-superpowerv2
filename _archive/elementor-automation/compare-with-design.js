/**
 * Compare Elementor template rendering with static HTML design
 * Identifies visual differences and generates comparison report
 */

const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const CONFIG = {
  staticDesignURL: 'file://' + path.resolve(__dirname, '../ridemaster-design/index.html'),
  elementorPageURL: 'https://staging4.ridemaster.eu/?p=348', // Update with actual page ID
  screenshotsDir: path.join(__dirname, 'screenshots', 'comparison'),
};

// Ensure screenshots directory exists
fs.mkdirSync(CONFIG.screenshotsDir, { recursive: true });

async function compareDesigns() {
  console.log('🔍 Comparing Elementor Template with Static Design...\n');

  const browser = await chromium.launch({
    headless: false,
    slowMo: 300,
  });

  const context = await browser.newContext({
    viewport: { width: 1920, height: 1080 },
  });

  try {
    // Capture static design
    console.log('📸 Capturing static HTML design...');
    const staticPage = await context.newPage();
    await staticPage.goto(CONFIG.staticDesignURL);
    await staticPage.waitForTimeout(2000);

    // Screenshot full page
    await staticPage.screenshot({
      path: path.join(CONFIG.screenshotsDir, '01-static-full.png'),
      fullPage: true,
    });

    // Screenshot hero section only
    const staticHero = await staticPage.locator('section.hero, .hero-section').first();
    if (await staticHero.count() > 0) {
      await staticHero.screenshot({
        path: path.join(CONFIG.screenshotsDir, '02-static-hero.png'),
      });
    }

    // Extract design metrics from static
    const staticMetrics = await staticPage.evaluate(() => {
      const hero = document.querySelector('section.hero, .hero-section, section');
      if (!hero) return null;

      const title = hero.querySelector('h1');
      const subtitle = hero.querySelector('p, .subtitle');
      const badges = hero.querySelectorAll('.trust-badge, .badge, [class*="badge"]');

      const styles = window.getComputedStyle(hero);

      return {
        hero: {
          height: hero.offsetHeight,
          backgroundColor: styles.backgroundColor,
          backgroundImage: styles.backgroundImage,
          padding: styles.padding,
        },
        title: title ? {
          text: title.textContent.trim(),
          fontSize: window.getComputedStyle(title).fontSize,
          fontWeight: window.getComputedStyle(title).fontWeight,
          color: window.getComputedStyle(title).color,
          textAlign: window.getComputedStyle(title).textAlign,
        } : null,
        subtitle: subtitle ? {
          text: subtitle.textContent.trim(),
          fontSize: window.getComputedStyle(subtitle).fontSize,
          color: window.getComputedStyle(subtitle).color,
        } : null,
        badgesCount: badges.length,
      };
    });

    console.log('✅ Static design captured\n');
    console.log('Static metrics:', JSON.stringify(staticMetrics, null, 2));
    console.log('');

    await staticPage.close();

    // Capture Elementor template
    console.log('📸 Capturing Elementor template...');
    const elementorPage = await context.newPage();
    await elementorPage.goto(CONFIG.elementorPageURL);
    await elementorPage.waitForTimeout(3000);

    // Screenshot full page
    await elementorPage.screenshot({
      path: path.join(CONFIG.screenshotsDir, '03-elementor-full.png'),
      fullPage: true,
    });

    // Screenshot hero section only
    const elementorHero = await elementorPage.locator('.elementor-section, .elementor-container').first();
    if (await elementorHero.count() > 0) {
      await elementorHero.screenshot({
        path: path.join(CONFIG.screenshotsDir, '04-elementor-hero.png'),
      });
    }

    // Extract metrics from Elementor
    const elementorMetrics = await elementorPage.evaluate(() => {
      const hero = document.querySelector('.elementor-section, .elementor-container');
      if (!hero) return null;

      const title = hero.querySelector('h1, .elementor-heading-title');
      const subtitle = hero.querySelector('p, .elementor-text-editor p');
      const badges = hero.querySelectorAll('.elementor-icon-box-wrapper');

      const styles = window.getComputedStyle(hero);

      return {
        hero: {
          height: hero.offsetHeight,
          backgroundColor: styles.backgroundColor,
          backgroundImage: styles.backgroundImage,
          padding: styles.padding,
        },
        title: title ? {
          text: title.textContent.trim(),
          fontSize: window.getComputedStyle(title).fontSize,
          fontWeight: window.getComputedStyle(title).fontWeight,
          color: window.getComputedStyle(title).color,
          textAlign: window.getComputedStyle(title).textAlign,
        } : null,
        subtitle: subtitle ? {
          text: subtitle.textContent.trim(),
          fontSize: window.getComputedStyle(subtitle).fontSize,
          color: window.getComputedStyle(subtitle).color,
        } : null,
        badgesCount: badges.length,
      };
    });

    console.log('✅ Elementor template captured\n');
    console.log('Elementor metrics:', JSON.stringify(elementorMetrics, null, 2));
    console.log('');

    await elementorPage.close();

    // Compare and generate report
    console.log('📊 Generating comparison report...\n');
    const differences = [];

    // Compare title
    if (staticMetrics?.title && elementorMetrics?.title) {
      if (staticMetrics.title.text !== elementorMetrics.title.text) {
        differences.push({
          element: 'Title Text',
          static: staticMetrics.title.text,
          elementor: elementorMetrics.title.text,
          severity: 'high',
        });
      }
      if (staticMetrics.title.fontSize !== elementorMetrics.title.fontSize) {
        differences.push({
          element: 'Title Font Size',
          static: staticMetrics.title.fontSize,
          elementor: elementorMetrics.title.fontSize,
          severity: 'medium',
        });
      }
      if (staticMetrics.title.color !== elementorMetrics.title.color) {
        differences.push({
          element: 'Title Color',
          static: staticMetrics.title.color,
          elementor: elementorMetrics.title.color,
          severity: 'medium',
        });
      }
    }

    // Compare badges count
    if (staticMetrics?.badgesCount !== elementorMetrics?.badgesCount) {
      differences.push({
        element: 'Trust Badges Count',
        static: staticMetrics?.badgesCount || 0,
        elementor: elementorMetrics?.badgesCount || 0,
        severity: 'high',
      });
    }

    // Generate report
    const report = {
      timestamp: new Date().toISOString(),
      staticURL: CONFIG.staticDesignURL,
      elementorURL: CONFIG.elementorPageURL,
      staticMetrics,
      elementorMetrics,
      differences,
      summary: {
        totalDifferences: differences.length,
        highSeverity: differences.filter(d => d.severity === 'high').length,
        mediumSeverity: differences.filter(d => d.severity === 'medium').length,
        lowSeverity: differences.filter(d => d.severity === 'low').length,
      },
    };

    // Save report
    const reportPath = path.join(CONFIG.screenshotsDir, 'comparison-report.json');
    fs.writeFileSync(reportPath, JSON.stringify(report, null, 2));
    console.log(`✅ Report saved: ${reportPath}\n`);

    // Print summary
    console.log('═'.repeat(70));
    console.log('COMPARISON SUMMARY');
    console.log('═'.repeat(70));

    if (differences.length === 0) {
      console.log('🎉 Perfect match! No differences found.');
    } else {
      console.log(`⚠️  Found ${differences.length} difference(s):\n`);

      differences.forEach((diff, idx) => {
        const icon = diff.severity === 'high' ? '🔴' : diff.severity === 'medium' ? '🟡' : '🟢';
        console.log(`${icon} ${idx + 1}. ${diff.element}`);
        console.log(`   Static:    ${diff.static}`);
        console.log(`   Elementor: ${diff.elementor}`);
        console.log('');
      });
    }

    console.log('═'.repeat(70));
    console.log('\n📸 Screenshots saved to:', CONFIG.screenshotsDir);
    console.log('📄 Detailed report:', reportPath);

    console.log('\n⏸️  Browser will stay open for 30 seconds...');
    await new Promise(resolve => setTimeout(resolve, 30000));

  } catch (error) {
    console.error('❌ Error:', error.message);
    console.error(error.stack);
  } finally {
    await browser.close();
    console.log('\n✅ Done!');
  }
}

compareDesigns().catch(console.error);
