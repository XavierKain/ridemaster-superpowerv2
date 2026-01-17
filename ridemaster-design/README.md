# RideMaster Design Prototype

A static HTML/CSS design prototype for RideMaster - a sport camp booking marketplace featuring the "Coastal Premium" aesthetic.

## Design System

This prototype uses a comprehensive design system with:

- **Primary Colors**: Ocean Teal (#0D9488 and variants)
- **Secondary Colors**: Sunset Coral/Orange (#F97316 and variants)
- **Accent Colors**: Gold (#F59E0B and variants)
- **Typography**: DM Sans font family
- **Spacing**: 8px grid system

## Project Structure

```
ridemaster-design/
├── css/
│   ├── tokens.css      # Design tokens (colors, spacing, typography)
│   ├── reset.css       # CSS reset for consistent styling
│   ├── base.css        # Base styles and typography
│   ├── layout.css      # Layout and grid systems
│   ├── components.css  # UI components (buttons, cards, forms)
│   └── pages.css       # Page-specific styles
├── js/
│   └── main.js         # Interactive functionality
├── assets/             # Images and other assets
└── *.html              # Page templates
```

## How to View

### Option 1: Open directly in browser
Simply double-click any `.html` file to open it in your default browser.

### Option 2: Use a local server (recommended)
For best results, use a local development server:

**Using Python:**
```bash
cd ridemaster-design
python -m http.server 8000
# Open http://localhost:8000 in your browser
```

**Using Node.js (npx):**
```bash
cd ridemaster-design
npx serve
# Open the provided URL in your browser
```

**Using VS Code Live Server:**
1. Install the "Live Server" extension
2. Right-click on any HTML file
3. Select "Open with Live Server"

## Pages

- `index.html` - Homepage with hero, sports, camps, and testimonials
- `camps.html` - Camp listing/search results page
- `camp-detail.html` - Individual camp detail page
- `coach-profile.html` - Coach profile page
- `dashboard.html` - User dashboard
- `auth.html` - Login/signup page
- `checkout.html` - Booking checkout page

## CSS Architecture

The CSS is organized in layers that should be loaded in order:

1. **tokens.css** - Design tokens and CSS custom properties
2. **reset.css** - Normalize browser defaults
3. **base.css** - Typography and base element styles
4. **layout.css** - Grid systems and layout components
5. **components.css** - Reusable UI components
6. **pages.css** - Page-specific styles

### Including All Styles

Add these to your HTML `<head>`:

```html
<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">

<!-- Stylesheets -->
<link rel="stylesheet" href="css/tokens.css">
<link rel="stylesheet" href="css/reset.css">
<link rel="stylesheet" href="css/base.css">
<link rel="stylesheet" href="css/layout.css">
<link rel="stylesheet" href="css/components.css">
<link rel="stylesheet" href="css/pages.css">
```

And before closing `</body>`:

```html
<script src="js/main.js"></script>
```

## Browser Support

This prototype is designed for modern browsers:
- Chrome (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)

## Notes

- This is a static design prototype for demonstration purposes
- No backend functionality is implemented
- Form submissions are non-functional
- All data shown is placeholder/demo content
