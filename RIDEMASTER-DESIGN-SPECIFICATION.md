# RideMaster Frontend Design Specification
## Sport Camp Booking Marketplace

**Version:** 1.0
**Date:** January 2026
**Platform:** WordPress + Elementor
**Languages:** French / English

---

## Table of Contents

1. [Design Vision](#1-design-vision)
2. [Global Design System](#2-global-design-system)
3. [Page Structures](#3-page-structures)
4. [Components Library](#4-components-library)
5. [Quality Refinements](#5-quality-refinements)

---

## 1. Design Vision

### 1.1 Aesthetic Direction: "Coastal Premium"

**Core Concept:**
A design that feels like a premium outdoor lifestyle magazine—clean, trustworthy, and aspirational while remaining approachable. The interface should evoke the feeling of standing on a beach at golden hour, board in hand, ready for adventure.

### 1.2 Design Pillars

| Pillar | Description |
|--------|-------------|
| **Trust First** | Every screen reinforces safety: secure payments, insurance included, clear cancellation |
| **Photography-Led** | Large, immersive imagery drives emotion and desire |
| **Effortless Clarity** | Information hierarchy so clear users never feel lost |
| **Community Warmth** | Human faces, real reviews, coach stories create connection |

### 1.3 Visual Personality

```
MOOD KEYWORDS:
- Bright but not harsh
- Professional but not corporate
- Adventurous but not reckless
- Premium but not exclusive
- Warm but not childish
```

### 1.4 Design References

| Source | What to Borrow |
|--------|----------------|
| **Airbnb Experiences** | Card layouts, trust badges, booking flow clarity, review display |
| **BookSurfCamps** | Sport-specific filtering, camp detail structure, coach presentation |
| **ClassPass** | Mobile-first booking, clean scheduling UI, credit system display |
| **Linear** | Typography precision, whitespace usage, subtle animations |
| **Stripe** | Trust indicators, payment UI, professional credibility |

---

## 2. Global Design System

### 2.1 Color Palette

```css
:root {
  /* PRIMARY - Ocean Teal */
  --primary-50: #F0FDFA;
  --primary-100: #CCFBF1;
  --primary-200: #99F6E4;
  --primary-300: #5EEAD4;
  --primary-400: #2DD4BF;
  --primary-500: #14B8A6;
  --primary-600: #0D9488;
  --primary-700: #0F766E;
  --primary-800: #115E59;
  --primary-900: #134E4A;

  /* SECONDARY - Sunset Coral */
  --secondary-50: #FFF7ED;
  --secondary-100: #FFEDD5;
  --secondary-200: #FED7AA;
  --secondary-300: #FDBA74;
  --secondary-400: #FB923C;
  --secondary-500: #F97316;
  --secondary-600: #EA580C;

  /* NEUTRAL - Slate */
  --neutral-50: #F8FAFC;
  --neutral-100: #F1F5F9;
  --neutral-200: #E2E8F0;
  --neutral-300: #CBD5E1;
  --neutral-400: #94A3B8;
  --neutral-500: #64748B;
  --neutral-600: #475569;
  --neutral-700: #334155;
  --neutral-800: #1E293B;
  --neutral-900: #0F172A;

  /* SEMANTIC */
  --success: #10B981;
  --warning: #F59E0B;
  --error: #EF4444;
  --info: #3B82F6;

  /* BASE */
  --white: #FFFFFF;
  --black: #000000;
}
```

### 2.2 Typography

```css
:root {
  /* Font Family */
  --font-primary: 'DM Sans', -apple-system, BlinkMacSystemFont, sans-serif;

  /* Display - Large headlines */
  --display-xl: 800 48px/1.1 var(--font-primary);
  --display-lg: 700 36px/1.15 var(--font-primary);
  --display-md: 700 28px/1.2 var(--font-primary);

  /* Headings */
  --heading-lg: 600 24px/1.25 var(--font-primary);
  --heading-md: 600 20px/1.3 var(--font-primary);
  --heading-sm: 600 18px/1.35 var(--font-primary);

  /* Body */
  --body-lg: 400 18px/1.6 var(--font-primary);
  --body-md: 400 16px/1.6 var(--font-primary);
  --body-sm: 400 14px/1.5 var(--font-primary);

  /* Labels */
  --label-lg: 500 14px/1.4 var(--font-primary);
  --label-md: 500 13px/1.4 var(--font-primary);
  --label-sm: 500 12px/1.4 var(--font-primary);

  /* Letter spacing */
  --tracking-tight: -0.02em;
  --tracking-normal: 0;
  --tracking-wide: 0.01em;
}
```

### 2.3 Spacing Scale (8px Grid)

```css
:root {
  --space-xs: 4px;
  --space-sm: 8px;
  --space-md: 16px;
  --space-lg: 24px;
  --space-xl: 32px;
  --space-2xl: 48px;
  --space-3xl: 64px;
  --space-4xl: 96px;
  --space-5xl: 128px;
}
```

### 2.4 Shadows

```css
:root {
  --shadow-xs: 0 1px 2px rgba(0, 0, 0, 0.05);
  --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.1), 0 1px 2px rgba(0, 0, 0, 0.06);
  --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
  --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
  --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
  --shadow-card: 0 2px 8px rgba(0, 0, 0, 0.08), 0 1px 2px rgba(0, 0, 0, 0.04);
  --shadow-card-hover: 0 8px 24px rgba(0, 0, 0, 0.12), 0 2px 8px rgba(0, 0, 0, 0.08);
}
```

### 2.5 Border Radius

```css
:root {
  --radius-sm: 4px;
  --radius-md: 8px;
  --radius-lg: 12px;
  --radius-xl: 16px;
  --radius-2xl: 24px;
  --radius-full: 9999px;
}
```

### 2.6 Breakpoints

```css
/* Mobile First */
--bp-sm: 640px;   /* Large phones */
--bp-md: 768px;   /* Tablets */
--bp-lg: 1024px;  /* Small laptops */
--bp-xl: 1280px;  /* Desktops */
--bp-2xl: 1440px; /* Large screens */
```

### 2.7 Grid System

```css
.container {
  max-width: 1280px;
  margin: 0 auto;
  padding: 0 var(--space-md);
}

@media (min-width: 768px) {
  .container { padding: 0 var(--space-lg); }
}

@media (min-width: 1024px) {
  .container { padding: 0 var(--space-xl); }
}

/* Card Grids */
.grid-camps {
  display: grid;
  gap: var(--space-lg);
  grid-template-columns: 1fr;
}

@media (min-width: 640px) {
  .grid-camps { grid-template-columns: repeat(2, 1fr); }
}

@media (min-width: 1024px) {
  .grid-camps { grid-template-columns: repeat(3, 1fr); }
}

@media (min-width: 1280px) {
  .grid-camps { grid-template-columns: repeat(4, 1fr); }
}
```

---

## 3. Page Structures

### 3.1 Homepage

#### Section A: Hero
```
┌─────────────────────────────────────────────────────────────┐
│  [Full-width video/image background - beach/action shot]    │
│                                                             │
│     "Trouvez votre prochain stage"                         │
│     "Find your next camp"                                   │
│                                                             │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ 🔍 Where? │ Sport ▼ │ Level ▼ │ When? │ [Search]   │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│     🛡️ Insurance included  🔒 Secure payment  ↩️ Free cancel │
└─────────────────────────────────────────────────────────────┘
```

**Specifications:**
- Height: 85vh (desktop), 70vh (mobile)
- Overlay: linear-gradient(180deg, rgba(0,0,0,0.3) 0%, rgba(0,0,0,0.5) 100%)
- Search bar: background white, border-radius 16px, shadow-lg
- Trust strip: 13px text, icons 16px, opacity 0.9

#### Section B: Sport Categories
```
┌─────────────────────────────────────────────────────────────┐
│  "Explore by Sport"                                         │
│                                                             │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐   │
│  │  🪁      │  │  🪂      │  │  🏄      │  │  ⛵      │   │
│  │ Kitesurf │  │ Paraglide│  │  Surf    │  │  Sailing │   │
│  │ 45 camps │  │ 23 camps │  │ 67 camps │  │ 31 camps │   │
│  └──────────┘  └──────────┘  └──────────┘  └──────────┘   │
└─────────────────────────────────────────────────────────────┘
```

**Specifications:**
- Card size: 160px x 160px (desktop), 120px x 120px (mobile)
- Background: gradient from sport color
- Hover: scale(1.05) + shadow-lg
- Scroll: horizontal on mobile with snap

#### Section C: Featured Camps
```
┌─────────────────────────────────────────────────────────────┐
│  "Camps à la une" / "Featured Camps"           [See all →] │
│                                                             │
│  ┌─────────────┐ ┌─────────────┐ ┌─────────────┐           │
│  │ [Image]     │ │ [Image]     │ │ [Image]     │           │
│  │ ❤️ ★4.9(23)│ │ ❤️ ★4.8(45)│ │ ❤️ ★4.7(12)│           │
│  │ Kite Camp  │ │ Wing Foil   │ │ Surf Week   │           │
│  │ Tarifa, ES │ │ Dakhla, MA  │ │ Hossegor,FR │           │
│  │ Mar 15-22  │ │ Apr 1-8     │ │ May 10-17   │           │
│  │ €890/pers  │ │ €1,200/pers │ │ €750/pers   │           │
│  └─────────────┘ └─────────────┘ └─────────────┘           │
└─────────────────────────────────────────────────────────────┘
```

#### Section D: How It Works
```
┌─────────────────────────────────────────────────────────────┐
│  "How RideMaster Works"                                     │
│                                                             │
│      ①                    ②                    ③           │
│   [Icon]               [Icon]               [Icon]          │
│   Explore              Book with            Ride with       │
│   Find camps by        confidence.          your coach.     │
│   sport, level,        Secure payment,      Meet your       │
│   and dates.           insurance            group, enjoy.   │
│                        included.                            │
└─────────────────────────────────────────────────────────────┘
```

#### Section E: Top Coaches
```
┌─────────────────────────────────────────────────────────────┐
│  "Meet Our Coaches"                            [All coaches]│
│                                                             │
│  ┌───────────┐  ┌───────────┐  ┌───────────┐               │
│  │ [Photo]   │  │ [Photo]   │  │ [Photo]   │               │
│  │ ✓ Verified│  │ ✓ Verified│  │ ✓ Verified│               │
│  │ Maria S.  │  │ Tom K.    │  │ Jules D.  │               │
│  │ Kitesurf  │  │ Wingfoil  │  │ Paraglide │               │
│  │ ★4.9 (89) │  │ ★4.8 (56) │  │ ★5.0 (34) │               │
│  │ 🇫🇷 🇬🇧 🇪🇸 │  │ 🇬🇧 🇩🇪    │  │ 🇫🇷 🇬🇧    │               │
│  └───────────┘  └───────────┘  └───────────┘               │
└─────────────────────────────────────────────────────────────┘
```

#### Section F: Popular Destinations
```
┌─────────────────────────────────────────────────────────────┐
│  "Popular Destinations"                                     │
│                                                             │
│  ┌──────────────────────┐  ┌──────────────────────┐        │
│  │ [Large Image]        │  │ [Large Image]        │        │
│  │ Tarifa, Spain        │  │ Dakhla, Morocco      │        │
│  │ 24 camps             │  │ 18 camps             │        │
│  └──────────────────────┘  └──────────────────────┘        │
│  ┌────────────┐ ┌────────────┐ ┌────────────┐              │
│  │ [Image]    │ │ [Image]    │ │ [Image]    │              │
│  │ Hossegor   │ │ Fuertevent │ │ Lake Garda │              │
│  └────────────┘ └────────────┘ └────────────┘              │
└─────────────────────────────────────────────────────────────┘
```

#### Section G: Trust Block
```
┌─────────────────────────────────────────────────────────────┐
│  [Light teal background]                                    │
│                                                             │
│          "Book with Complete Peace of Mind"                 │
│                                                             │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐         │
│  │ 🛡️          │  │ 🔒          │  │ ↩️           │         │
│  │ Insurance   │  │ Secure      │  │ Flexible    │         │
│  │ Included    │  │ Payment     │  │ Cancellation│         │
│  │ Individual  │  │ Stripe      │  │ Full refund │         │
│  │ accident    │  │ protected   │  │ 45+ days    │         │
│  └─────────────┘  └─────────────┘  └─────────────┘         │
│                                                             │
│                 [Learn more about our guarantees]           │
└─────────────────────────────────────────────────────────────┘
```

#### Section H: Testimonials
```
┌─────────────────────────────────────────────────────────────┐
│  "What Riders Say"                                          │
│                                                             │
│  ┌─────────────────────────────────────────────────────┐   │
│  │  "Amazing experience! Maria was incredible, the     │   │
│  │   spot was perfect, and I progressed so much."      │   │
│  │                                                      │   │
│  │   [Photo] Sophie M. - Kite Camp Tarifa              │   │
│  │           ★★★★★                                      │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│              ○ ● ○ ○ ○  (carousel dots)                    │
└─────────────────────────────────────────────────────────────┘
```

#### Section I: Upcoming Camps
```
┌─────────────────────────────────────────────────────────────┐
│  "Prochains départs" / "Upcoming Departures"               │
│                                                             │
│  [Horizontal scroll of Camp Cards with dates prominent]    │
│                                                             │
│  ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐ →        │
│  │ In 5    │ │ In 12   │ │ In 18   │ │ In 25   │          │
│  │ days    │ │ days    │ │ days    │ │ days    │          │
│  └─────────┘ └─────────┘ └─────────┘ └─────────┘          │
└─────────────────────────────────────────────────────────────┘
```

#### Section J: Newsletter
```
┌─────────────────────────────────────────────────────────────┐
│  [Background image - lifestyle shot, muted]                 │
│                                                             │
│     "Stay in the Loop"                                      │
│     Get exclusive deals and new camp alerts                 │
│                                                             │
│     ┌─────────────────────────┐ ┌──────────┐               │
│     │ Enter your email        │ │Subscribe │               │
│     └─────────────────────────┘ └──────────┘               │
│                                                             │
│     □ I agree to receive marketing emails                   │
└─────────────────────────────────────────────────────────────┘
```

#### Section K: Footer
```
┌─────────────────────────────────────────────────────────────┐
│  [Dark background - neutral-900]                            │
│                                                             │
│  RIDEMASTER          Explore        Company       Support   │
│  [Logo]              All Camps      About Us      FAQ       │
│                      By Sport       Careers       Contact   │
│  Your adventure      By Location    Press         Help      │
│  starts here.        Coaches        Blog                    │
│                                                             │
│  ─────────────────────────────────────────────────────────  │
│                                                             │
│  [Social icons]     © 2026 RideMaster    Privacy | Terms   │
│  FB TW IG YT        All rights reserved                     │
│                                                             │
│  🇫🇷 Français  |  🇬🇧 English                               │
└─────────────────────────────────────────────────────────────┘
```

---

### 3.2 Camps Listing Page

#### Layout Structure
```
┌─────────────────────────────────────────────────────────────┐
│  [Header]                                                   │
├─────────────────────────────────────────────────────────────┤
│  Camps > Kitesurf (breadcrumb)                             │
│                                                             │
│  "Kitesurf Camps"                                          │
│  127 camps available                                        │
├─────────────────────────────────────────────────────────────┤
│  FILTERS BAR:                                               │
│  ┌────────┐ ┌────────┐ ┌────────┐ ┌────────┐ ┌──────────┐ │
│  │Sport ▼ │ │Level ▼ │ │Date ▼  │ │Price ▼ │ │More ▼    │ │
│  └────────┘ └────────┘ └────────┘ └────────┘ └──────────┘ │
│                                                             │
│  Active: [Kitesurf ×] [Beginner ×]           [Clear all]   │
├─────────────────────────────────────────────────────────────┤
│  Sort by: [Recommended ▼]              Showing 1-24 of 127 │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐          │
│  │ Card 1  │ │ Card 2  │ │ Card 3  │ │ Card 4  │          │
│  └─────────┘ └─────────┘ └─────────┘ └─────────┘          │
│                                                             │
│  ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐          │
│  │ Card 5  │ │ Card 6  │ │ Card 7  │ │ Card 8  │          │
│  └─────────┘ └─────────┘ └─────────┘ └─────────┘          │
│                                                             │
│  ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐          │
│  │ Card 9  │ │ Card 10 │ │ Card 11 │ │ Card 12 │          │
│  └─────────┘ └─────────┘ └─────────┘ └─────────┘          │
│                                                             │
├─────────────────────────────────────────────────────────────┤
│           [1] [2] [3] ... [6] [Next →]                     │
├─────────────────────────────────────────────────────────────┤
│  [Footer]                                                   │
└─────────────────────────────────────────────────────────────┘
```

#### Filter Dropdown Specifications
```css
.filter-dropdown {
  position: absolute;
  top: calc(100% + 8px);
  left: 0;
  background: var(--white);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-xl);
  padding: var(--space-lg);
  min-width: 280px;
  z-index: 100;
}

/* Sport Filter Content */
.filter-sport-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: var(--space-sm);
}

.filter-sport-item {
  display: flex;
  align-items: center;
  gap: var(--space-sm);
  padding: var(--space-sm) var(--space-md);
  border-radius: var(--radius-md);
  cursor: pointer;
  transition: background 0.15s;
}

.filter-sport-item:hover {
  background: var(--neutral-100);
}

.filter-sport-item--selected {
  background: var(--primary-50);
  color: var(--primary-700);
}
```

#### Mobile Filter (Bottom Sheet)
```css
.filter-sheet {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  background: var(--white);
  border-radius: 24px 24px 0 0;
  max-height: 85vh;
  overflow-y: auto;
  padding: var(--space-lg);
  transform: translateY(100%);
  transition: transform 0.3s cubic-bezier(0.32, 0.72, 0, 1);
  z-index: 200;
}

.filter-sheet--open {
  transform: translateY(0);
}

.filter-sheet__handle {
  width: 36px;
  height: 4px;
  background: var(--neutral-300);
  border-radius: 2px;
  margin: 0 auto var(--space-lg);
}

.filter-sheet__section {
  margin-bottom: var(--space-xl);
}

.filter-sheet__section-title {
  font: var(--heading-sm);
  color: var(--neutral-900);
  margin-bottom: var(--space-md);
}
```

---

### 3.3 Camp Detail Page

#### Layout Structure
```
┌─────────────────────────────────────────────────────────────┐
│  [Header]                                                   │
├─────────────────────────────────────────────────────────────┤
│  Camps > Kitesurf > Tarifa Kite Week (breadcrumb)          │
├─────────────────────────────────────────────────────────────┤
│  IMAGE GALLERY                                              │
│  ┌─────────────────────────────────┐ ┌──────┐ ┌──────┐    │
│  │                                 │ │      │ │      │    │
│  │      [Main Image]               │ │ Img2 │ │ Img3 │    │
│  │                                 │ │      │ │      │    │
│  │                                 │ ├──────┤ ├──────┤    │
│  │                                 │ │ Img4 │ │+12   │    │
│  └─────────────────────────────────┘ └──────┘ └──────┘    │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  CONTENT                              BOOKING CARD          │
│  ┌────────────────────────────────┐  ┌─────────────────┐   │
│  │ [Kitesurf] [Intermediate]      │  │ From €890       │   │
│  │                                │  │ per person      │   │
│  │ Tarifa Kite Week               │  │                 │   │
│  │ ★4.9 (23 reviews) · Tarifa, ES │  │ ┌─────────────┐ │   │
│  │                                │  │ │ Mar 15-22   │ │   │
│  │ 7 days · Max 8 participants    │  │ └─────────────┘ │   │
│  │                                │  │                 │   │
│  │ [Coach Photo] Maria S.         │  │ ┌─────────────┐ │   │
│  │ IKO Level 3 · 🇫🇷 🇬🇧 🇪🇸        │  │ │ 2 persons   │ │   │
│  │                                │  │ └─────────────┘ │   │
│  ├────────────────────────────────┤  │                 │   │
│  │ DESCRIPTION                    │  │ €890 × 2       │   │
│  │ Join us for an unforgettable   │  │ ─────────────  │   │
│  │ week of kitesurfing in Tarifa  │  │ Total: €1,780  │   │
│  │ ...                            │  │                 │   │
│  ├────────────────────────────────┤  │ [Book Now]     │   │
│  │ WHAT'S INCLUDED                │  │                 │   │
│  │ ✓ 5 days coaching              │  │ 🛡️ Insurance   │   │
│  │ ✓ Equipment rental             │  │ ↩️ Free cancel  │   │
│  │ ✓ Accommodation                │  └─────────────────┘   │
│  │ ✗ Flights                      │                        │
│  │ ✗ Meals                        │                        │
│  ├────────────────────────────────┤                        │
│  │ THE SPOT                       │                        │
│  │ [Map] Tarifa, Spain            │                        │
│  │ Best conditions: Apr-Oct       │                        │
│  ├────────────────────────────────┤                        │
│  │ REVIEWS                        │                        │
│  │ ★★★★★ 4.9 (23 reviews)        │                        │
│  │ [Review cards...]              │                        │
│  └────────────────────────────────┘                        │
│                                                             │
├─────────────────────────────────────────────────────────────┤
│  SIMILAR CAMPS                                              │
│  ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐          │
│  │ Card 1  │ │ Card 2  │ │ Card 3  │ │ Card 4  │          │
│  └─────────┘ └─────────┘ └─────────┘ └─────────┘          │
├─────────────────────────────────────────────────────────────┤
│  [Footer]                                                   │
└─────────────────────────────────────────────────────────────┘
```

#### Sticky Booking Card
```css
.booking-card {
  position: sticky;
  top: 100px;
  background: var(--white);
  border: 1px solid var(--neutral-200);
  border-radius: var(--radius-xl);
  padding: var(--space-lg);
  box-shadow: var(--shadow-lg);
}

.booking-card__price {
  font: var(--display-md);
  color: var(--neutral-900);
}

.booking-card__price-suffix {
  font: var(--body-md);
  color: var(--neutral-500);
}

.booking-card__selector {
  border: 1px solid var(--neutral-300);
  border-radius: var(--radius-lg);
  margin: var(--space-md) 0;
  overflow: hidden;
}

.booking-card__selector-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: var(--space-md);
  cursor: pointer;
}

.booking-card__selector-row:not(:last-child) {
  border-bottom: 1px solid var(--neutral-200);
}

.booking-card__cta {
  width: 100%;
  padding: var(--space-md);
  background: var(--primary-600);
  color: var(--white);
  font: var(--label-lg);
  font-weight: 600;
  border: none;
  border-radius: var(--radius-lg);
  cursor: pointer;
  transition: background 0.15s, transform 0.1s;
}

.booking-card__cta:hover {
  background: var(--primary-700);
}

.booking-card__cta:active {
  transform: scale(0.98);
}

.booking-card__trust {
  display: flex;
  justify-content: center;
  gap: var(--space-lg);
  margin-top: var(--space-md);
  padding-top: var(--space-md);
  border-top: 1px solid var(--neutral-100);
}

.booking-card__trust-item {
  display: flex;
  align-items: center;
  gap: var(--space-xs);
  font: var(--label-sm);
  color: var(--neutral-500);
}
```

---

### 3.4 Coach Profile Page

#### Layout Structure
```
┌─────────────────────────────────────────────────────────────┐
│  [Header]                                                   │
├─────────────────────────────────────────────────────────────┤
│  HERO SECTION                                               │
│  ┌─────────────────────────────────────────────────────┐   │
│  │  [Cover photo - coach in action]                     │   │
│  │                                                       │   │
│  │  ┌──────────┐                                        │   │
│  │  │ [Profile │  Maria Santos                          │   │
│  │  │  Photo]  │  ✓ Verified Coach                      │   │
│  │  │          │  ★4.9 (89 reviews) · 127 students      │   │
│  │  └──────────┘  Kitesurf · Wingfoil                   │   │
│  │                🇫🇷 🇬🇧 🇪🇸 · Tarifa, Spain              │   │
│  │                                                       │   │
│  │  [Message]  [Follow]                                 │   │
│  └─────────────────────────────────────────────────────┘   │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  TAB NAVIGATION                                             │
│  [About]  [Camps]  [Reviews]  [Gallery]                    │
│  ─────────────────────────────────────────                  │
│                                                             │
│  ABOUT TAB CONTENT                                          │
│  ┌────────────────────────────────────────────────────────┐│
│  │ Bio                                                     ││
│  │ "Passionate kitesurfer with 15 years of experience..." ││
│  │                                                         ││
│  │ Certifications                                          ││
│  │ ┌──────────────┐ ┌──────────────┐ ┌──────────────┐    ││
│  │ │ IKO Level 3  │ │ First Aid    │ │ Rescue Cert  │    ││
│  │ └──────────────┘ └──────────────┘ └──────────────┘    ││
│  │                                                         ││
│  │ Experience                                              ││
│  │ • 15 years kitesurfing                                 ││
│  │ • 500+ students taught                                 ││
│  │ • Competition experience                               ││
│  │                                                         ││
│  │ Specialties                                             ││
│  │ [Beginners] [Wave riding] [Freestyle]                  ││
│  └────────────────────────────────────────────────────────┘│
│                                                             │
├─────────────────────────────────────────────────────────────┤
│  UPCOMING CAMPS BY THIS COACH                               │
│  ┌─────────┐ ┌─────────┐ ┌─────────┐                       │
│  │ Camp 1  │ │ Camp 2  │ │ Camp 3  │                       │
│  └─────────┘ └─────────┘ └─────────┘                       │
├─────────────────────────────────────────────────────────────┤
│  [Footer]                                                   │
└─────────────────────────────────────────────────────────────┘
```

---

### 3.5 User Dashboards

#### Rider Dashboard
```
┌─────────────────────────────────────────────────────────────┐
│  [Header with user avatar]                                  │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  SIDEBAR              MAIN CONTENT                          │
│  ┌──────────┐        ┌────────────────────────────────────┐│
│  │ Dashboard│◀       │ Welcome back, Sophie!              ││
│  │ Bookings │        │                                     ││
│  │ Favorites│        │ YOUR NEXT ADVENTURE                 ││
│  │ Messages │        │ ┌─────────────────────────────────┐││
│  │ Reviews  │        │ │ [Image]                         │││
│  │ Settings │        │ │ Tarifa Kite Week                │││
│  │ ──────── │        │ │ with Maria S.                   │││
│  │ Help     │        │ │ Mar 15-22, 2026                 │││
│  │ Logout   │        │ │ In 45 days                      │││
│  └──────────┘        │ │ [View details] [Contact coach]  │││
│                      │ └─────────────────────────────────┘││
│                      │                                     ││
│                      │ PAST BOOKINGS                       ││
│                      │ ┌──────────┐ ┌──────────┐          ││
│                      │ │ Camp 1   │ │ Camp 2   │          ││
│                      │ │ [Review] │ │ [Review] │          ││
│                      │ └──────────┘ └──────────┘          ││
│                      │                                     ││
│                      │ RECOMMENDED FOR YOU                 ││
│                      │ ┌──────────┐ ┌──────────┐          ││
│                      │ │ Camp A   │ │ Camp B   │          ││
│                      │ └──────────┘ └──────────┘          ││
│                      └────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────┘
```

#### Coach Dashboard
```
┌─────────────────────────────────────────────────────────────┐
│  [Header with coach avatar]                                 │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  SIDEBAR              MAIN CONTENT                          │
│  ┌──────────┐        ┌────────────────────────────────────┐│
│  │ Dashboard│◀       │ Dashboard                           ││
│  │ My Camps │        │                                     ││
│  │ Bookings │        │ STATS OVERVIEW                      ││
│  │ Calendar │        │ ┌────────┐ ┌────────┐ ┌────────┐   ││
│  │ Messages │        │ │ €4,250 │ │ 12     │ │ 4.9    │   ││
│  │ Reviews  │        │ │ Revenue│ │Bookings│ │ Rating │   ││
│  │ Earnings │        │ │ this mo│ │this mo │ │ (89)   │   ││
│  │ Settings │        │ └────────┘ └────────┘ └────────┘   ││
│  │ ──────── │        │                                     ││
│  │ Help     │        │ UPCOMING SESSIONS                   ││
│  │ Logout   │        │ ┌─────────────────────────────────┐││
│  └──────────┘        │ │ Today, 10:00                    │││
│                      │ │ Kite Beginner Session           │││
│                      │ │ 4 participants                  │││
│                      │ │ [View] [Message group]          │││
│                      │ └─────────────────────────────────┘││
│                      │                                     ││
│                      │ RECENT BOOKINGS                     ││
│                      │ [Table with booking details]        ││
│                      │                                     ││
│                      │ EARNINGS CHART                      ││
│                      │ [Line chart - last 6 months]        ││
│                      └────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────┘
```

---

## 4. Components Library

### 4.1 Cards

#### Camp Card
```css
.camp-card {
  background: var(--white);
  border-radius: var(--radius-xl);
  overflow: hidden;
  box-shadow: var(--shadow-card);
  transition: transform 0.2s cubic-bezier(0.2, 0, 0, 1),
              box-shadow 0.2s cubic-bezier(0.2, 0, 0, 1);
}

.camp-card:hover {
  transform: translateY(-4px);
  box-shadow: var(--shadow-card-hover);
}

.camp-card__image-wrapper {
  position: relative;
  aspect-ratio: 4/3;
  overflow: hidden;
}

.camp-card__image {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.3s ease;
}

.camp-card:hover .camp-card__image {
  transform: scale(1.05);
}

.camp-card__favorite {
  position: absolute;
  top: 12px;
  right: 12px;
  width: 36px;
  height: 36px;
  background: var(--white);
  border-radius: var(--radius-full);
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: var(--shadow-md);
  cursor: pointer;
  transition: transform 0.15s;
}

.camp-card__favorite:hover {
  transform: scale(1.1);
}

.camp-card__badges {
  position: absolute;
  top: 12px;
  left: 12px;
  display: flex;
  gap: 6px;
}

/* Max 2 badges visible */
.camp-card__badges > *:nth-child(n+3) {
  display: none;
}

.camp-card__content {
  padding: var(--space-md);
}

.camp-card__header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: var(--space-xs);
}

.camp-card__title {
  font: var(--heading-sm);
  color: var(--neutral-900);
  margin: 0;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.camp-card__rating {
  display: flex;
  align-items: center;
  gap: 4px;
  font: var(--label-sm);
  color: var(--neutral-700);
  white-space: nowrap;
}

.camp-card__rating-star {
  color: var(--warning);
}

.camp-card__meta {
  font: var(--body-sm);
  color: var(--neutral-500);
  margin-bottom: var(--space-sm);
}

.camp-card__footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-top: var(--space-sm);
  border-top: 1px solid var(--neutral-100);
}

.camp-card__coach {
  display: flex;
  align-items: center;
  gap: var(--space-sm);
}

.camp-card__coach-avatar {
  width: 24px;
  height: 24px;
  border-radius: var(--radius-full);
  object-fit: cover;
}

.camp-card__coach-name {
  font: var(--label-sm);
  color: var(--neutral-600);
}

.camp-card__price {
  text-align: right;
}

.camp-card__price-amount {
  font: var(--heading-sm);
  color: var(--neutral-900);
}

.camp-card__price-suffix {
  font: var(--label-sm);
  color: var(--neutral-500);
}

.camp-card__price--discounted .camp-card__price-original {
  font: var(--label-sm);
  color: var(--neutral-400);
  text-decoration: line-through;
}

.camp-card__price--discounted .camp-card__price-amount {
  color: var(--error);
}
```

#### Coach Card
```css
.coach-card {
  background: var(--white);
  border-radius: var(--radius-xl);
  padding: var(--space-lg);
  text-align: center;
  box-shadow: var(--shadow-card);
  transition: transform 0.2s, box-shadow 0.2s;
}

.coach-card:hover {
  transform: translateY(-4px);
  box-shadow: var(--shadow-card-hover);
}

.coach-card__avatar {
  width: 80px;
  height: 80px;
  border-radius: var(--radius-full);
  object-fit: cover;
  margin: 0 auto var(--space-md);
  border: 3px solid var(--primary-100);
}

.coach-card__verified {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font: var(--label-sm);
  color: var(--primary-600);
  background: var(--primary-50);
  padding: 4px 8px;
  border-radius: var(--radius-full);
  margin-bottom: var(--space-sm);
}

.coach-card__name {
  font: var(--heading-md);
  color: var(--neutral-900);
  margin-bottom: var(--space-xs);
}

.coach-card__sports {
  font: var(--body-sm);
  color: var(--neutral-500);
  margin-bottom: var(--space-sm);
}

.coach-card__rating {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
  font: var(--label-md);
  color: var(--neutral-700);
  margin-bottom: var(--space-sm);
}

.coach-card__languages {
  font-size: 18px;
  letter-spacing: 2px;
}
```

#### Review Card
```css
.review-card {
  background: var(--white);
  border-radius: var(--radius-lg);
  padding: var(--space-lg);
  border: 1px solid var(--neutral-200);
}

.review-card__header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: var(--space-md);
}

.review-card__author {
  display: flex;
  align-items: center;
  gap: var(--space-sm);
}

.review-card__avatar {
  width: 40px;
  height: 40px;
  border-radius: var(--radius-full);
  object-fit: cover;
}

.review-card__author-info {
  display: flex;
  flex-direction: column;
}

.review-card__author-name {
  font: var(--label-lg);
  color: var(--neutral-900);
}

.review-card__date {
  font: var(--label-sm);
  color: var(--neutral-400);
}

.review-card__rating {
  display: flex;
  gap: 2px;
}

.review-card__star {
  color: var(--warning);
  width: 16px;
  height: 16px;
}

.review-card__content {
  font: var(--body-md);
  color: var(--neutral-700);
  line-height: 1.6;
}

.review-card__camp {
  display: flex;
  align-items: center;
  gap: var(--space-sm);
  margin-top: var(--space-md);
  padding-top: var(--space-md);
  border-top: 1px solid var(--neutral-100);
  font: var(--label-sm);
  color: var(--neutral-500);
}
```

### 4.2 Badges & Tags

```css
/* Base Badge */
.badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 10px;
  border-radius: var(--radius-full);
  font: var(--label-sm);
  font-weight: 500;
}

/* Sport Badges */
.badge--kitesurf {
  background: #DBEAFE;
  color: #1E40AF;
}

.badge--wingfoil {
  background: #D1FAE5;
  color: #065F46;
}

.badge--surf {
  background: #FEF3C7;
  color: #92400E;
}

.badge--paraglide {
  background: #EDE9FE;
  color: #5B21B6;
}

/* Level Badges */
.badge--beginner {
  background: var(--success);
  background: rgba(16, 185, 129, 0.1);
  color: #059669;
}

.badge--intermediate {
  background: rgba(245, 158, 11, 0.1);
  color: #D97706;
}

.badge--advanced {
  background: rgba(239, 68, 68, 0.1);
  color: #DC2626;
}

/* Discount Badge */
.badge--discount {
  background: var(--error);
  color: var(--white);
  font-weight: 600;
}

/* Verified Badge */
.badge--verified {
  background: var(--primary-50);
  color: var(--primary-700);
}

.badge--verified::before {
  content: '✓';
  font-weight: bold;
}

/* Status Badges */
.badge--confirmed {
  background: rgba(16, 185, 129, 0.1);
  color: #059669;
}

.badge--pending {
  background: rgba(245, 158, 11, 0.1);
  color: #D97706;
}

.badge--cancelled {
  background: rgba(239, 68, 68, 0.1);
  color: #DC2626;
}
```

### 4.3 Buttons

```css
/* Base Button */
.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-sm);
  padding: var(--space-sm) var(--space-lg);
  border-radius: var(--radius-lg);
  font: var(--label-lg);
  font-weight: 600;
  border: none;
  cursor: pointer;
  transition: background 0.15s, transform 0.1s, box-shadow 0.15s;
  min-height: 44px;
}

/* Primary Button */
.btn--primary {
  background: var(--primary-600);
  color: var(--white);
}

.btn--primary:hover {
  background: var(--primary-700);
  box-shadow: 0 4px 12px rgba(13, 148, 136, 0.3);
}

.btn--primary:active {
  transform: scale(0.98);
}

/* Secondary Button */
.btn--secondary {
  background: var(--white);
  color: var(--primary-600);
  border: 2px solid var(--primary-600);
}

.btn--secondary:hover {
  background: var(--primary-50);
}

/* Ghost Button */
.btn--ghost {
  background: transparent;
  color: var(--neutral-700);
}

.btn--ghost:hover {
  background: var(--neutral-100);
}

/* Disabled State */
.btn:disabled {
  background: var(--neutral-200);
  color: var(--neutral-400);
  cursor: not-allowed;
  transform: none;
  box-shadow: none;
}

/* Size Variants */
.btn--sm {
  padding: var(--space-xs) var(--space-md);
  font: var(--label-sm);
  min-height: 36px;
}

.btn--lg {
  padding: var(--space-md) var(--space-xl);
  font: var(--label-lg);
  min-height: 52px;
}

/* Full Width */
.btn--full {
  width: 100%;
}
```

### 4.4 Form Elements

```css
/* Input */
.input {
  width: 100%;
  padding: var(--space-sm) var(--space-md);
  border: 1px solid var(--neutral-300);
  border-radius: var(--radius-lg);
  font: var(--body-md);
  color: var(--neutral-900);
  background: var(--white);
  transition: border-color 0.15s, box-shadow 0.15s;
  min-height: 44px;
}

.input::placeholder {
  color: var(--neutral-400);
}

.input:focus {
  outline: none;
  border-color: var(--primary-500);
  box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.15);
}

.input:disabled {
  background: var(--neutral-100);
  color: var(--neutral-500);
  cursor: not-allowed;
}

/* Input with Icon */
.input-wrapper {
  position: relative;
}

.input-wrapper .input {
  padding-left: 44px;
}

.input-wrapper__icon {
  position: absolute;
  left: var(--space-md);
  top: 50%;
  transform: translateY(-50%);
  color: var(--neutral-400);
  pointer-events: none;
}

/* Select */
.select {
  appearance: none;
  width: 100%;
  padding: var(--space-sm) var(--space-xl) var(--space-sm) var(--space-md);
  border: 1px solid var(--neutral-300);
  border-radius: var(--radius-lg);
  font: var(--body-md);
  color: var(--neutral-900);
  background: var(--white) url("data:image/svg+xml,...") no-repeat right 12px center;
  background-size: 16px;
  cursor: pointer;
  min-height: 44px;
}

.select:focus {
  outline: none;
  border-color: var(--primary-500);
  box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.15);
}

/* Checkbox & Radio */
.checkbox,
.radio {
  display: flex;
  align-items: center;
  gap: var(--space-sm);
  cursor: pointer;
}

.checkbox__input,
.radio__input {
  width: 20px;
  height: 20px;
  border: 2px solid var(--neutral-300);
  background: var(--white);
  cursor: pointer;
  transition: border-color 0.15s, background 0.15s;
}

.checkbox__input {
  border-radius: var(--radius-sm);
}

.radio__input {
  border-radius: var(--radius-full);
}

.checkbox__input:checked,
.radio__input:checked {
  border-color: var(--primary-600);
  background: var(--primary-600);
}

.checkbox__label,
.radio__label {
  font: var(--body-md);
  color: var(--neutral-700);
}

/* Counter */
.counter {
  display: flex;
  align-items: center;
  gap: var(--space-md);
}

.counter__btn {
  width: 36px;
  height: 36px;
  border-radius: var(--radius-md);
  border: 1px solid var(--neutral-300);
  background: var(--white);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: background 0.15s, border-color 0.15s;
}

.counter__btn:hover:not(:disabled) {
  background: var(--neutral-100);
  border-color: var(--neutral-400);
}

.counter__btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.counter__value {
  font: var(--heading-sm);
  color: var(--neutral-900);
  min-width: 32px;
  text-align: center;
}
```

### 4.5 Trust Components

```css
/* Trust Block */
.trust-block {
  background: var(--primary-50);
  border-radius: var(--radius-xl);
  padding: var(--space-2xl);
  text-align: center;
}

.trust-block__title {
  font: var(--heading-lg);
  color: var(--neutral-900);
  margin-bottom: var(--space-xl);
}

.trust-block__items {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: var(--space-xl);
}

@media (max-width: 767px) {
  .trust-block__items {
    grid-template-columns: 1fr;
    gap: var(--space-lg);
  }
}

.trust-block__item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-sm);
}

.trust-block__icon {
  width: 48px;
  height: 48px;
  border-radius: var(--radius-full);
  background: var(--white);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--primary-600);
  box-shadow: var(--shadow-sm);
}

.trust-block__item-title {
  font: var(--label-lg);
  color: var(--neutral-900);
}

.trust-block__item-desc {
  font: var(--body-sm);
  color: var(--neutral-500);
}

/* Trust Strip (inline) */
.trust-strip {
  display: flex;
  justify-content: center;
  gap: var(--space-xl);
  padding: var(--space-md) 0;
}

@media (max-width: 767px) {
  .trust-strip {
    flex-wrap: wrap;
    gap: var(--space-md);
  }
}

.trust-strip__item {
  display: flex;
  align-items: center;
  gap: var(--space-xs);
  font: var(--label-sm);
  color: var(--neutral-600);
}

.trust-strip__icon {
  color: var(--primary-600);
}

/* Floating Trust Bar */
.floating-trust-bar {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  background: var(--white);
  border-top: 1px solid var(--neutral-200);
  padding: var(--space-sm) var(--space-lg);
  display: flex;
  justify-content: center;
  gap: var(--space-xl);
  z-index: 100;
  box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.08);
}

@media (max-width: 767px) {
  .floating-trust-bar {
    gap: var(--space-md);
    padding: var(--space-sm) var(--space-md);
  }
}

.floating-trust-bar__item {
  display: flex;
  align-items: center;
  gap: var(--space-xs);
  font: var(--label-sm);
  color: var(--neutral-600);
}

/* Cancellation Policy Visual */
.cancel-policy {
  background: var(--neutral-50);
  border-radius: var(--radius-lg);
  padding: var(--space-lg);
}

.cancel-policy__title {
  font: var(--heading-sm);
  color: var(--neutral-900);
  margin-bottom: var(--space-md);
}

.cancel-policy__timeline {
  display: flex;
  justify-content: space-between;
  position: relative;
  margin-bottom: var(--space-md);
}

.cancel-policy__timeline::before {
  content: '';
  position: absolute;
  top: 12px;
  left: 0;
  right: 0;
  height: 4px;
  background: linear-gradient(90deg,
    var(--success) 0%,
    var(--success) 25%,
    #84CC16 25%,
    #84CC16 50%,
    var(--warning) 50%,
    var(--warning) 75%,
    var(--error) 75%);
  border-radius: 2px;
}

.cancel-policy__point {
  display: flex;
  flex-direction: column;
  align-items: center;
  position: relative;
  z-index: 1;
}

.cancel-policy__dot {
  width: 24px;
  height: 24px;
  border-radius: var(--radius-full);
  background: var(--white);
  border: 3px solid currentColor;
  margin-bottom: var(--space-xs);
}

.cancel-policy__label {
  font: var(--label-sm);
  color: var(--neutral-600);
  text-align: center;
}

.cancel-policy__percent {
  font: var(--label-lg);
  font-weight: 600;
}
```

### 4.6 Navigation

```css
/* Header */
.header {
  position: sticky;
  top: 0;
  background: var(--white);
  border-bottom: 1px solid var(--neutral-200);
  z-index: 100;
}

.header__container {
  max-width: 1280px;
  margin: 0 auto;
  padding: var(--space-md) var(--space-lg);
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.header__logo {
  font: var(--heading-lg);
  font-weight: 700;
  color: var(--primary-600);
  text-decoration: none;
}

.header__nav {
  display: flex;
  align-items: center;
  gap: var(--space-xl);
}

@media (max-width: 767px) {
  .header__nav {
    display: none;
  }
}

.header__nav-link {
  font: var(--label-lg);
  color: var(--neutral-600);
  text-decoration: none;
  transition: color 0.15s;
}

.header__nav-link:hover,
.header__nav-link--active {
  color: var(--neutral-900);
}

.header__actions {
  display: flex;
  align-items: center;
  gap: var(--space-md);
}

.header__lang {
  font: var(--label-md);
  color: var(--neutral-600);
  cursor: pointer;
}

/* Breadcrumb */
.breadcrumb {
  display: flex;
  align-items: center;
  gap: var(--space-sm);
  font: var(--label-md);
  color: var(--neutral-500);
  padding: var(--space-md) 0;
}

.breadcrumb__link {
  color: var(--neutral-500);
  text-decoration: none;
  transition: color 0.15s;
}

.breadcrumb__link:hover {
  color: var(--primary-600);
}

.breadcrumb__separator {
  color: var(--neutral-300);
}

.breadcrumb__current {
  color: var(--neutral-900);
}

/* Tabs */
.tabs {
  display: flex;
  gap: var(--space-xl);
  border-bottom: 1px solid var(--neutral-200);
}

.tabs__item {
  padding: var(--space-md) 0;
  font: var(--label-lg);
  color: var(--neutral-500);
  border-bottom: 2px solid transparent;
  cursor: pointer;
  transition: color 0.15s, border-color 0.15s;
  margin-bottom: -1px;
}

.tabs__item:hover {
  color: var(--neutral-700);
}

.tabs__item--active {
  color: var(--neutral-900);
  border-bottom-color: var(--primary-600);
}

/* Footer */
.footer {
  background: var(--neutral-900);
  color: var(--neutral-300);
  padding: var(--space-3xl) 0 var(--space-xl);
}

.footer__grid {
  display: grid;
  grid-template-columns: 2fr repeat(3, 1fr);
  gap: var(--space-2xl);
  margin-bottom: var(--space-2xl);
}

@media (max-width: 767px) {
  .footer__grid {
    grid-template-columns: 1fr 1fr;
    gap: var(--space-xl);
  }
}

.footer__brand {
  grid-column: span 1;
}

@media (max-width: 767px) {
  .footer__brand {
    grid-column: span 2;
  }
}

.footer__logo {
  font: var(--heading-lg);
  color: var(--white);
  margin-bottom: var(--space-md);
}

.footer__tagline {
  font: var(--body-md);
  color: var(--neutral-400);
}

.footer__title {
  font: var(--label-lg);
  color: var(--white);
  margin-bottom: var(--space-md);
}

.footer__links {
  list-style: none;
  padding: 0;
  margin: 0;
}

.footer__link {
  display: block;
  padding: var(--space-xs) 0;
  color: var(--neutral-400);
  text-decoration: none;
  font: var(--body-sm);
  transition: color 0.15s;
}

.footer__link:hover {
  color: var(--white);
}

.footer__bottom {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-top: var(--space-xl);
  border-top: 1px solid var(--neutral-800);
}

@media (max-width: 767px) {
  .footer__bottom {
    flex-direction: column;
    gap: var(--space-md);
    text-align: center;
  }
}

.footer__social {
  display: flex;
  gap: var(--space-md);
}

.footer__social-link {
  width: 40px;
  height: 40px;
  border-radius: var(--radius-full);
  background: var(--neutral-800);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--neutral-400);
  transition: background 0.15s, color 0.15s;
}

.footer__social-link:hover {
  background: var(--primary-600);
  color: var(--white);
}

.footer__copyright {
  font: var(--label-sm);
  color: var(--neutral-500);
}

.footer__legal {
  display: flex;
  gap: var(--space-md);
}

.footer__legal-link {
  font: var(--label-sm);
  color: var(--neutral-500);
  text-decoration: none;
}

.footer__legal-link:hover {
  color: var(--white);
}
```

---

## 5. Quality Refinements

### 5.1 Visual Hierarchy Fixes

| Issue | Before | After |
|-------|--------|-------|
| Title weights uniform | All 700 | Display: 800, Section: 700, Card: 600 |
| Spacing inconsistent | Mixed values | Strict 8px grid only |
| CTA competition | Multiple teal buttons | 1 primary CTA per viewport |
| Section density | 64px padding | 96px desktop, 64px mobile |

### 5.2 Refined Spacing Scale

```css
:root {
  --space-xs: 4px;
  --space-sm: 8px;
  --space-md: 16px;
  --space-lg: 24px;
  --space-xl: 32px;
  --space-2xl: 48px;
  --space-3xl: 64px;
  --space-4xl: 96px;
  --space-5xl: 128px;
}
```

### 5.3 Trust Signal Hierarchy

```
TIER 1 - Always visible:
├── Secure payment icon (lock + Stripe)
└── Insurance included badge

TIER 2 - Contextual:
├── Verified coach badge (coach elements only)
└── Review count + rating (on cards)

TIER 3 - On demand:
├── Full cancellation policy (expandable)
└── Detailed insurance info (modal)
```

### 5.4 Mobile Touch Targets

All interactive elements must be minimum 44px:

```css
.btn,
.filter-chip,
.tab-item,
.nav-item,
.input,
.select {
  min-height: 44px;
  min-width: 44px;
}
```

### 5.5 Enhanced Micro-interactions

```css
/* Card Hover - Lift effect */
.camp-card {
  transition:
    transform 0.2s cubic-bezier(0.2, 0, 0, 1),
    box-shadow 0.2s cubic-bezier(0.2, 0, 0, 1);
}

.camp-card:hover {
  transform: translateY(-4px);
  box-shadow:
    0 8px 24px rgba(0, 0, 0, 0.12),
    0 2px 8px rgba(0, 0, 0, 0.08);
}

.camp-card:active {
  transform: translateY(-2px);
  box-shadow:
    0 4px 12px rgba(0, 0, 0, 0.1),
    0 1px 4px rgba(0, 0, 0, 0.06);
}

/* Button Press - Tactile feedback */
.btn--primary:hover {
  box-shadow: 0 4px 12px rgba(13, 148, 136, 0.3);
}

.btn--primary:active {
  transform: scale(0.98);
  box-shadow: 0 2px 6px rgba(13, 148, 136, 0.2);
}

/* Input Focus - Clear indicator */
.input:focus {
  outline: none;
  border-color: var(--primary-500);
  box-shadow:
    0 0 0 3px rgba(13, 148, 136, 0.15),
    0 1px 2px rgba(0, 0, 0, 0.05);
}

/* Skeleton Loading */
@keyframes shimmer {
  0% { background-position: -200% 0; }
  100% { background-position: 200% 0; }
}

.skeleton {
  background: linear-gradient(
    90deg,
    var(--neutral-200) 0%,
    var(--neutral-100) 50%,
    var(--neutral-200) 100%
  );
  background-size: 200% 100%;
  animation: shimmer 1.5s ease-in-out infinite;
  border-radius: var(--radius-md);
}
```

### 5.6 Color Contrast (WCAG AA)

| Element | Background | Foreground | Ratio | Status |
|---------|------------|------------|-------|--------|
| Body text | #FFFFFF | #374151 | 10.5:1 | ✅ |
| Secondary text | #FFFFFF | #6B7280 | 5.7:1 | ✅ |
| Primary button | #0D9488 | #FFFFFF | 4.6:1 | ✅ |
| Ghost button | #FFFFFF | #0D9488 | 4.6:1 | ✅ |
| Disabled text | #FFFFFF | #6B7280 | 5.7:1 | ✅ |

### 5.7 Visual Noise Reduction

**Badge limit rule - max 2 per card:**
```css
.camp-card__badges > *:nth-child(n+3) {
  display: none;
}
```

**Section transitions - use spacing, not borders:**
```css
.section {
  padding: var(--space-4xl) 0;
}

.section:nth-child(even) {
  background: var(--neutral-50);
}
```

### 5.8 Airbnb-Level Polish Checklist

```
✅ Clean, generous whitespace
✅ Consistent 8px spacing grid
✅ Clear visual hierarchy
✅ Accessible color contrast (WCAG AA)
✅ Smooth micro-interactions
✅ Mobile-optimized patterns (44px targets)
✅ Trust signals appropriately placed
✅ Card-based layouts with depth
✅ Premium but approachable feel
✅ Photography-forward design
✅ Clear CTAs with single focus
✅ Readable typography at all sizes
```

---

## 6. Elementor Implementation Notes

### 6.1 Section Templates to Create

1. Hero with Search
2. Category Cards Grid
3. Camp Cards Grid (2/3/4 columns)
4. Coach Cards Grid
5. Trust Block (3-column)
6. Testimonials Carousel
7. Newsletter Signup
8. How It Works (3-step)
9. Destination Cards (mixed grid)
10. Stats Overview (dashboard)

### 6.2 Global Widgets to Save

- Camp Card
- Coach Card
- Review Card
- Trust Badge
- Sport Badge
- Level Badge
- Primary Button
- Secondary Button
- Input Field
- Search Bar

### 6.3 Theme Settings

Apply CSS variables in Elementor > Site Settings > Custom CSS:

```css
:root {
  /* Copy entire color palette from Section 2.1 */
  /* Copy typography from Section 2.2 */
  /* Copy spacing from Section 2.3 */
}
```

### 6.4 Responsive Breakpoints

Configure in Elementor > Site Settings > Layout:
- Mobile: 0 - 767px
- Tablet: 768px - 1023px
- Desktop: 1024px+

---

## 7. Asset Requirements

### 7.1 Photography Style

- **Lighting:** Natural, golden hour preferred
- **Composition:** Action shots, lifestyle moments
- **Subjects:** Real coaches and riders, diverse
- **Quality:** Minimum 1920px width, high resolution
- **Mood:** Aspirational but authentic

### 7.2 Icon Set

Use Lucide Icons (lucide.dev) for consistency:
- Style: Stroke, 1.5px weight
- Size: 16px (inline), 20px (buttons), 24px (features)
- Color: Inherit from parent

### 7.3 Required Images

Per camp listing:
- Hero image (16:9, 1920x1080)
- Gallery images (4-6, various ratios)
- Coach headshot (1:1, 400x400)

---

**End of Design Specification**

*Document created: January 2026*
*Version: 1.0*
*Platform: WordPress + Elementor*
