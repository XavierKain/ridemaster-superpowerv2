# How to Use Elementor Templates - User Guide

**Audience:** WordPress administrators, developers
**Prerequisites:** Elementor Pro, JetEngine (for dynamic content)
**Status:** Production-ready templates

---

## Table of Contents

1. [Quick Start](#quick-start)
2. [Homepage Hero Template](#homepage-hero-template)
3. [Camp Card Loop Item](#camp-card-loop-item)
4. [Camp Detail Single Template](#camp-detail-single-template)
5. [Connecting Dynamic Data](#connecting-dynamic-data)
6. [Troubleshooting](#troubleshooting)

---

## Quick Start

### Option 1: Import via REST API (Recommended)

```bash
cd elementor-automation

# Import Hero Template
node import-via-api.js

# Import Camp Card
node import-camp-card-via-api.js

# Import Camp Detail
node import-camp-detail-via-api.js
```

### Option 2: Manual Import via Elementor UI

1. Go to **Templates > Saved Templates**
2. Click **Import Templates**
3. Select the JSON file from `generated-templates/` folder
4. Click **Import Now**

⚠️ **Note:** REST API method is more reliable and includes validation.

---

## Homepage Hero Template

### Where to Use

- Homepage top section
- Landing pages
- Campaign pages

### Template File

`generated-templates/hero-auto-v10-with-globals.json`

### How to Apply

#### Method 1: Insert into Page

1. Edit your homepage in Elementor
2. Press `Cmd+Shift+L` (Mac) or `Ctrl+Shift+L` (Windows) to open Template Library
3. Go to **Templates** tab
4. Search for "Homepage Hero - v10"
5. Click **Insert**

#### Method 2: Save as Reusable Section

1. In Elementor editor, right-click the hero container
2. Select **Save as Template**
3. Name it "Hero Section"
4. Reuse on other pages via Template Library

### Customization Guide

#### Replace Search Bar Placeholder

The template includes a placeholder for the search bar. To replace it:

1. Select the placeholder widget
2. Delete it
3. Insert your preferred search form plugin:
   - **JetSmartFilters** search bar
   - **SearchWP** form
   - Custom HTML form

#### Update Text Content

**Title:**
- Select the heading widget with "Find Your Next Camp"
- Edit text directly or connect to dynamic field
- Typography already uses `rm_display` global

**Subtitle:**
- Select the text-editor widget
- Update the description
- Typography already uses global settings

#### Change Background Image

1. Select the main hero container
2. Go to **Style** tab > **Background**
3. Replace image URL
4. Adjust overlay gradient if needed

#### Customize Trust Badges

Located in white container at bottom:

**To change badge text:**
1. Expand the trust-badges-container
2. Each badge has icon + text widgets
3. Edit text in text-editor widgets

**To add/remove badges:**
1. Duplicate an existing badge container
2. Update icon and text
3. Or delete unwanted badge containers

---

## Camp Card Loop Item

### Where to Use

- Camp archive/listing pages
- Homepage "Featured Camps" section
- Search results
- Coach profile "Available Camps" section

### Template File

`generated-templates/camp-card-loop-v1.json`

### How to Apply

#### Step 1: Import the Loop Item Template

```bash
node import-camp-card-via-api.js
```

Or import manually via Elementor > Templates > Import.

#### Step 2: Create a Loop Grid

1. Edit the page where you want to show camp cards
2. Add a **Loop Grid** widget (Elementor Pro required)
3. In Loop Grid settings:
   - **Query:** Select "Custom Query"
   - **Post Type:** Select "camp" (or your CPT name)
   - **Posts Per Page:** 8 (or desired number)

#### Step 3: Connect the Loop Item Template

1. In Loop Grid settings, go to **Layout** tab
2. **Template:** Select "Camp Card - Loop Item v1"
3. **Columns:** 4 (desktop), 2 (tablet), 1 (mobile)
4. **Gap:** 24px (or as desired)

#### Step 4: Connect Dynamic Data

The template has placeholder content. Replace with dynamic fields:

**Featured Image:**
- Already has `{{featured_image_url}}` placeholder
- Or use Dynamic Tags: **Post** > **Featured Image**

**Camp Title:**
- Select the heading widget
- Click Dynamic Tags icon
- Select **Post** > **Title**

**Location:**
- Select location text widget
- Dynamic Tags > **JetEngine** > **camp_location** (or your field name)

**Dates:**
- Dynamic Tags > **JetEngine** > **camp_start_date**
- Or use Date Range field

**Price:**
- Dynamic Tags > **JetEngine** > **camp_price**
- Add currency symbol in prefix/suffix settings

**Rating:**
- Use JetEngine Average Rating field
- Or custom ACF/meta field

**Coach Info:**
- Coach Avatar: Dynamic Tags > **Post** > **Author Picture**
  - Or JetEngine relation field for coach CPT
- Coach Name: Dynamic Tags > **Post** > **Author Name**
  - Or JetEngine relation field

**Sport Badge:**
- Dynamic Tags > **Taxonomy** > **camp_sport** (term name)
- Style with conditional CSS or JetEngine conditions

**Level Badge:**
- Dynamic Tags > **JetEngine** > **camp_level** field

#### Step 5: Configure Conditional Visibility

Some elements should show/hide based on data:

**Favorite Button:**
- Add conditional logic: Show only if user is logged in
- Or integrate with favorite/wishlist plugin

**Badges:**
- Show sport badge only if taxonomy term exists
- Show level badge only if level field has value

---

## Camp Detail Single Template

### Where to Use

- Individual camp post pages
- Single CPT view

### Template File

`generated-templates/camp-detail-single-v1.json`

### How to Apply

#### Step 1: Import the Template

```bash
node import-camp-detail-via-api.js
```

#### Step 2: Apply via Theme Builder

1. Go to **Templates > Theme Builder**
2. Click **Add New** > **Single**
3. **Template Type:** Single
4. **Apply To:** Camp (select your CPT)
5. Click **Edit with Elementor**
6. Press `Cmd+Shift+L` to open Template Library
7. Search "Camp Detail - Single v1"
8. Click **Insert**
9. **Publish** and set display conditions

#### Step 3: Connect Dynamic Content

**Breadcrumb:**
- Manually update links or use breadcrumb plugin
- Or replace with dynamic breadcrumb widget

**Camp Title:**
- Dynamic Tags > **Post** > **Title**

**Badges:**
- Sport: Dynamic Tags > **Taxonomy** > **camp_sport**
- Level: Dynamic Tags > **JetEngine** > **camp_level**

**Meta Information:**

**Rating:**
- Dynamic Tags > **JetEngine** > **Average Rating**
- Or custom rating field

**Location:**
- Dynamic Tags > **JetEngine** > **camp_location**

**Duration:**
- Dynamic Tags > **JetEngine** > **camp_duration**
- Or calculated from start/end dates

**Capacity:**
- Dynamic Tags > **JetEngine** > **camp_max_participants**

**Coach Section:**

**Coach Avatar:**
- Dynamic Tags > **JetEngine** > **camp_coach** (relation field) > **Featured Image**

**Coach Name:**
- Dynamic Tags > **JetEngine** > **camp_coach** (relation field) > **Title**

**Coach Certification:**
- Dynamic Tags > **JetEngine** > **camp_coach** > **coach_certification** field

**Description:**
- Dynamic Tags > **Post** > **Excerpt** or **Content**
- Or JetEngine custom field

**Booking Card:**

**Price:**
- Dynamic Tags > **JetEngine** > **camp_price**

**Reserve Button:**
- Set link to booking page with query parameters:
  - URL: `/checkout?camp_id={post_id}`
  - Or use WooCommerce product link

#### Step 4: Add Missing Sections (Optional)

The template includes core sections. Add these for full functionality:

**Image Gallery:**
1. Add new section above title
2. Insert **Gallery** widget
3. Dynamic Tags > **Post** > **Gallery** or JetEngine gallery field
4. Style as 2fr + 4x 1fr grid (main image + thumbnails)

**What's Included:**
1. Add section after description
2. Create 2-column grid
3. Use **Icon List** widget or manual icon + text pairs
4. Dynamic Tags > JetEngine repeater field for inclusions

**The Spot:**
1. Add section for spot information
2. Insert **Google Maps** widget (Elementor Pro)
3. Dynamic Tags > **JetEngine** > **camp_spot** (relation) > **spot_coordinates**
4. Add spot details (wind, temperature, season)

**Reviews:**
1. Use native WordPress comments
2. Or JetEngine Reviews addon
3. Or third-party review plugin (e.g., WP Customer Reviews)

---

## Connecting Dynamic Data

### JetEngine Meta Fields

#### Text Fields
```
Dynamic Tags > JetEngine > [Field Name]
```

#### Relation Fields (Coach, Spot)
```
Dynamic Tags > JetEngine > [Relation Field] > Related Post > [Field]
```

Example: Coach Name
```
Dynamic Tags > JetEngine > camp_coach > Related Post > Title
```

#### Repeater Fields (Inclusions, FAQ)
```
Use JetEngine Listing Grid widget
Template: Create mini loop item template
```

#### Date Fields
```
Dynamic Tags > JetEngine > [Date Field]
Format: In widget settings > Date Format
```

#### Gallery Fields
```
Dynamic Tags > JetEngine > [Gallery Field]
Widget: Gallery widget set to slideshow/grid
```

### Conditional Logic

#### Show/Hide Based on Field Value

1. Select the element/widget
2. Go to **Advanced** tab
3. **Conditional Display**
4. Add condition:
   - **Field:** Custom Field or Taxonomy
   - **Operator:** Is not empty / Equals / Contains
   - **Value:** [value to compare]

Example: Show coach section only if coach exists
```
Field: camp_coach
Operator: Is not empty
```

#### Show/Hide Based on User

Example: Show booking card only to logged-in users
```
Field: User State
Operator: Is
Value: Logged In
```

### Dynamic Colors/Styles

#### Sport-Specific Badge Colors

Use JetEngine Dynamic CSS:

```css
.badge--kitesurf { background: #ECFEFF; color: #0891B2; }
.badge--wingfoil { background: #F5F3FF; color: #7C3AED; }
.badge--surf { background: #F0FDFA; color: #0D9488; }
```

Add dynamic class:
```
Dynamic Tags > JetEngine > camp_sport (slug)
Prefix: badge--
```

---

## Troubleshooting

### Template Not Showing in Library

**Solution:**
1. Refresh the Elementor template library cache
2. Go to **Elementor > Tools > Regenerate Files**
3. Click **Regenerate Files & Data**
4. Clear browser cache
5. Try importing again

### Images Not Displaying

**Issue:** Placeholder `{{featured_image_url}}` not replaced

**Solution:**
- Replace with Dynamic Tags > Post > Featured Image
- Or ensure JetEngine dynamic tags are configured

### Styles Not Applying

**Issue:** Global colors/typography not loading

**Solution:**
1. Ensure Elementor Kit settings are configured
2. Go to **Elementor > Site Settings > Global Colors**
3. Verify all custom colors exist (rm_slate_600, rm_white, etc.)
4. Same for typography (rm_display, rm_hero_title, etc.)
5. Regenerate CSS files

### Booking Card Not Sticky

**Issue:** Sidebar not sticking on scroll

**Solution:**
1. Select the booking-sidebar container
2. **Advanced** tab > **Position**: Sticky
3. **Vertical Offset**: 100px (or adjust for header height)
4. Ensure parent container allows sticky positioning

### Loop Grid Empty

**Issue:** No camp cards showing

**Solution:**
1. Check Query settings: Post Type = "camp"
2. Verify camps exist and are published
3. Check template assignment in Loop Grid > Layout > Template
4. Ensure Loop Item template type is set to "loop-item"

### Dynamic Data Not Loading

**Issue:** Fields showing as empty or not connecting

**Solution:**
1. Verify JetEngine fields are created for "camp" CPT
2. Check field slugs match (camp_price, camp_location, etc.)
3. Ensure posts have data in those fields
4. Clear Elementor cache
5. Test with static text first, then add dynamic tags

### Responsive Layout Issues

**Issue:** Layout breaks on mobile/tablet

**Solution:**
1. Check responsive settings for each container
2. 2-column layouts should stack on mobile:
   - Desktop: `flex-direction: row`
   - Mobile: `flex-direction: column`
3. Adjust font sizes for mobile breakpoints
4. Test in Elementor's responsive preview mode

---

## Best Practices

### 1. Always Use Globals

When customizing:
- Colors: Use `__globals__` references instead of hardcoded hex
- Typography: Use global typography presets
- This ensures consistency and easy theme-wide updates

### 2. Create Template Variations

Don't edit the original imported template:
1. Duplicate the template
2. Rename it (e.g., "Camp Card - Homepage Variant")
3. Make customizations
4. Keep original as fallback

### 3. Test Dynamic Data Early

Before applying to all pages:
1. Test with 1-2 sample posts
2. Verify all dynamic fields load correctly
3. Check conditional logic works
4. Then apply site-wide

### 4. Use Version Control

When making template changes:
1. Export current version (Elementor > Tools > Export Template)
2. Make changes
3. Test thoroughly
4. Keep export as backup

### 5. Monitor Performance

Templates with many widgets can affect load time:
- Use lazy loading for images
- Minimize custom CSS/JS
- Enable Elementor's optimization features
- Use caching plugin (WP Rocket, etc.)

---

## Additional Resources

- **Main Guide:** [ELEMENTOR-TEMPLATE-GUIDE.md](./ELEMENTOR-TEMPLATE-GUIDE.md)
- **Templates Summary:** [TEMPLATES-SUMMARY.md](./TEMPLATES-SUMMARY.md)
- **Elementor Documentation:** https://elementor.com/help/
- **JetEngine Documentation:** https://crocoblock.com/knowledge-base/jetengine/

---

## Support

For template-specific issues:
1. Check [ELEMENTOR-TEMPLATE-GUIDE.md](./ELEMENTOR-TEMPLATE-GUIDE.md) troubleshooting section
2. Verify field names and slugs match your CPT structure
3. Test with static content first, then add dynamic data
4. Clear all caches (Elementor, WordPress, browser)

**Staging Site:** https://staging4.ridemaster.eu/wp-admin

---

**Last Updated:** 2026-01-19
**Template Version:** v1 (all templates)
**Elementor Version:** Pro 3.x
**WordPress Version:** 6.x
