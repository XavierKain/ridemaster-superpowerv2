# RideMaster Plugin Migration Guide

## Overview

Two new plugins replace 22 Code Snippets + 2 standalone plugins:

| New Plugin | Replaces |
|-----------|----------|
| **RideMaster** (main) | 16 snippets + ridemaster-inline-edit + ridemaster-coach-status-column |
| **RideMaster UI Tweaks** | 6 snippets |

---

## Step 1: Upload the new plugins

Upload these folders to `wp-content/plugins/`:
- `ridemaster/`
- `ridemaster-ui-tweaks/`

**Do NOT activate yet.**

---

## Step 2: Deactivate old plugins

In WP Admin > Plugins, **deactivate**:
- RideMaster Inline Edit
- RideMaster Coach Status Column

---

## Step 3: Deactivate Code Snippets

In Code Snippets, **deactivate** these 22 snippets:

### Replaced by "RideMaster" plugin (16):
1. Auto coach_post_id on init (ensure coach has coach_post_id)
2. Link coach post to user (wp_insert_post)
3. Auto coach title from first_name + last_name
4. Coach Dashboard Sidebar shortcodes (rm_coach_avatar, rm_coach_name)
5. Coach profile URL shortcode (rm_coach_profile_url)
6. Override JFB preset post ID on profile page
7. Dashboard query (current_coach_post_id in REQUEST)
8. Camp creation initialization (save_post_product)
9. Auto-link Coach to Spot via Camp
10. Coach Status Column (admin)
11. Auto-publish on "active" status (set_object_terms)
12. Login/redirect logic (role-based redirects)
13. Guest photo upload (REST endpoint + JS)
14. Media library restriction (coaches see own uploads)
15. Cascade deletion (User > Coach > Camps)
16. Bypass logout confirmation

### Replaced by "RideMaster UI Tweaks" plugin (6):
17. Custom Quantity Selector +/- (CSS + JS)
18. "Book Now" button text (WooCommerce)
19. Hide WooCommerce info messages (CSS)
20. Camp creation form CSS (JetFormBuilder styling)
21. Flatpickr on create-camp page
22. JFB file upload preview fix (JS)

---

## Step 4: Activate new plugins

In WP Admin > Plugins, **activate**:
1. RideMaster
2. RideMaster UI Tweaks

---

## Step 5: Test

Verify these work:
- [ ] Coach registration flow (guest upload, redirect to pending page)
- [ ] Coach login redirects (validated > dashboard, pending > waiting, suspended > suspended page)
- [ ] Coach dashboard loads (sidebar avatar + name, camp list)
- [ ] Coach profile inline editing works
- [ ] Camp inline editing works
- [ ] Camp creation form (styling, flatpickr, file upload, checkboxes)
- [ ] Single product page (quantity selector, Book Now button)
- [ ] Cart page (quantity selector, no WC info messages)
- [ ] WP Admin: Coach status column + filter + inline change
- [ ] Coach validation email sent on status change
- [ ] Deleting a coach cascades to camps

---

## Snippets to KEEP ACTIVE (4)

These are NOT included in any plugin and must stay as Code Snippets:

1. **Date Range callback** (format_date_range) — JetEngine/Elementor display
2. **Language flags shortcode** ([language_flags]) — display utility
3. **Focus visible outline none** — global CSS tweak
4. **Force Elementor icons Safari** — browser compatibility fix

---

## Rollback

If something breaks:
1. Deactivate "RideMaster" and "RideMaster UI Tweaks"
2. Reactivate the old plugins (Inline Edit, Coach Status Column)
3. Reactivate all 22 Code Snippets
