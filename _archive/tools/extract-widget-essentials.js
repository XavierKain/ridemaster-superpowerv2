#!/usr/bin/env node

/**
 * Extract essential widgets from elementor-schema.json
 */

const fs = require('fs');
const path = require('path');

console.log('🔍 Loading elementor-schema.json...\n');

const schemaPath = path.join(__dirname, '../elementor-teaching/elementor-schema.json');
const schema = JSON.parse(fs.readFileSync(schemaPath, 'utf-8'));

console.log(`✅ Loaded schema from ${schema.generated_at}`);
console.log(`📦 Elementor version: ${schema.elementor_version}\n`);

// Widgets we need for RideMaster
const essentialWidgets = [
  'heading',
  'text-editor',
  'image',
  'button',
  'icon',
  'star-rating',
  'image-box',
  'image-gallery',
  'html'
];

console.log(`📋 Extracting ${essentialWidgets.length} essential widgets:\n`);

const extracted = {
  generated_at: new Date().toISOString(),
  source: 'elementor-schema.json',
  elementor_version: schema.elementor_version,
  widgets: {}
};

essentialWidgets.forEach(widgetName => {
  const widget = schema.widgets[widgetName];

  if (!widget) {
    console.log(`  ❌ ${widgetName} - NOT FOUND`);
    return;
  }

  console.log(`  ✅ ${widgetName} - ${widget.title || 'No title'}`);

  // Extract essential info
  extracted.widgets[widgetName] = {
    name: widget.name,
    title: widget.title,
    icon: widget.icon,
    categories: widget.categories,
    controls: {}
  };

  // Group controls by section for easier reading
  const sections = {};

  Object.entries(widget.controls || {}).forEach(([key, control]) => {
    const section = control.section || 'general';
    if (!sections[section]) {
      sections[section] = {};
    }

    // Only keep essential properties
    sections[section][key] = {
      type: control.type,
      label: control.label,
      default: control.default,
      responsive: control.responsive,
      selectors: control.selectors ? Object.keys(control.selectors) : [],
      condition: control.condition
    };
  });

  extracted.widgets[widgetName].sections = sections;

  // Count controls
  const controlCount = Object.keys(widget.controls || {}).length;
  console.log(`     → ${controlCount} controls`);
});

// Save extracted data
const outputPath = path.join(__dirname, '../docs/elementor-widgets-essential.json');
fs.mkdirSync(path.dirname(outputPath), { recursive: true });
fs.writeFileSync(outputPath, JSON.stringify(extracted, null, 2));

console.log(`\n💾 Saved to: docs/elementor-widgets-essential.json`);
console.log(`📊 File size: ${(fs.statSync(outputPath).size / 1024).toFixed(2)} KB\n`);
