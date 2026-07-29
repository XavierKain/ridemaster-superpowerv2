/**
 * Script Node.js pour analyser un widget Elementor
 *
 * Usage: node analyze-elementor-widget.js <widget-name>
 * Example: node analyze-elementor-widget.js heading
 */

const fs = require('fs');
const path = require('path');

const widgetName = process.argv[2] || 'heading';
const schemaPath = path.join(__dirname, '../elementor-teaching/elementor-schema.json');

console.log(`🔍 Analyzing widget: ${widgetName}\n`);

// Lire le schema
const schema = JSON.parse(fs.readFileSync(schemaPath, 'utf-8'));

// Trouver le widget
const widget = schema.widgets?.[widgetName] || schema[widgetName];

if (!widget) {
  console.error(`❌ Widget "${widgetName}" not found in schema`);
  console.log(`\n📋 Available widgets:`);
  console.log(Object.keys(schema.widgets || schema).slice(0, 20).join(', '));
  process.exit(1);
}

console.log(`✅ Found widget: ${widget.title || widgetName}\n`);

// Afficher les controls par section
console.log('📊 CONTROLS BREAKDOWN:\n');

const controls = widget.controls || {};
const sections = {};

// Grouper par section
Object.entries(controls).forEach(([key, control]) => {
  const section = control.section || 'general';
  if (!sections[section]) {
    sections[section] = [];
  }
  sections[section].push({ key, ...control });
});

// Afficher chaque section
Object.entries(sections).forEach(([sectionName, sectionControls]) => {
  console.log(`\n### ${sectionName}`);
  console.log('─'.repeat(50));

  sectionControls.forEach(control => {
    console.log(`\n  ${control.key}`);
    console.log(`    Type: ${control.type}`);
    if (control.label) console.log(`    Label: ${control.label}`);
    if (control.default !== undefined) {
      console.log(`    Default: ${JSON.stringify(control.default)}`);
    }
    if (control.responsive) console.log(`    Responsive: YES`);
    if (control.selectors && Object.keys(control.selectors).length > 0) {
      console.log(`    Has CSS selectors`);
    }
  });
});

// Créer un exemple minimal
console.log('\n\n📝 MINIMAL WIDGET STRUCTURE:\n');
const minimalWidget = {
  id: "abc12345",
  elType: "widget",
  widgetType: widgetName,
  settings: {},
  elements: []
};

// Ajouter les defaults essentiels
Object.entries(controls).forEach(([key, control]) => {
  if (control.default !== undefined && control.default !== '' && control.default !== null) {
    minimalWidget.settings[key] = control.default;
  }
});

console.log(JSON.stringify(minimalWidget, null, 2));

// Sauvegarder dans un fichier
const outputPath = path.join(__dirname, `../docs/widget-${widgetName}-analysis.json`);
fs.mkdirSync(path.dirname(outputPath), { recursive: true });
fs.writeFileSync(outputPath, JSON.stringify({
  widget: widgetName,
  title: widget.title,
  sections,
  minimalStructure: minimalWidget
}, null, 2));

console.log(`\n\n💾 Full analysis saved to: docs/widget-${widgetName}-analysis.json`);
