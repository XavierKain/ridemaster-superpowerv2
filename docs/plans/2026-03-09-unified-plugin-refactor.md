# RideMaster Unified Plugin Refactor

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Consolidate 16 business-logic snippets + 2 existing plugins into a single `ridemaster/` plugin, and group 6 UI/presentation snippets into a separate `ridemaster-ui-tweaks/` plugin.

**Architecture:** Single main class `RideMaster` bootstraps modular includes, each handling a domain (Coach, Camp, Auth, Admin, Cleanup, Inline Edit). No class inheritance — each module registers its own hooks in its constructor. The UI tweaks plugin is standalone with no dependencies on the main plugin.

**Tech Stack:** WordPress hooks/filters, WooCommerce API, JetEngine Relations API, JetFormBuilder integration, WordPress REST API.

---

## Plugin 1: ridemaster/ (Main Plugin)

### File Structure

```
plugins/ridemaster/
├── ridemaster.php                    (bootstrap + constants)
├── includes/
│   ├── class-coach.php              (coach identity & profile)
│   ├── class-camp.php               (camp creation & relations)
│   ├── class-auth.php               (auth, redirects, guest upload)
│   ├── class-admin.php              (wp-admin features)
│   ├── class-cleanup.php            (data integrity & cascade delete)
│   └── class-inline-edit.php        (existing inline-edit, moved)
└── assets/
    ├── css/inline-edit.css          (existing)
    └── js/inline-edit.js            (existing)
```

### Module Breakdown

#### ridemaster.php (bootstrap)
- Plugin header (Name, Version, Description)
- ABSPATH guard
- Define constants: `RM_VERSION`, `RM_PLUGIN_DIR`, `RM_PLUGIN_URL`
- Require all includes
- Instantiate all classes

#### class-coach.php — RM_Coach
Snippets integrated:
1. Auto coach_post_id on init (ensure each coach user has their coach post)
2. Link coach post to user on wp_insert_post
3. Auto coach title from first_name + last_name meta
4. [rm_coach_avatar] shortcode
5. [rm_coach_name] shortcode
6. [rm_coach_profile_url] shortcode
7. Override JFB preset post ID on profile page
8. Dashboard sidebar CSS (wp_head)
9. Dashboard query (set current_coach_post_id in $_REQUEST)

Hooks:
- `init` → ensure_coach_post_id()
- `wp_insert_post` → link_coach_post_to_user()
- `added_post_meta` / `updated_post_meta` → auto_coach_title()
- `template_redirect` → inject_jfb_preset()
- `wp` → set_dashboard_query_var()
- `wp_head` → sidebar_css()
- Shortcodes: rm_coach_avatar, rm_coach_name, rm_coach_profile_url

#### class-camp.php — RM_Camp
Snippets integrated:
1. Camp creation initialization (save_post_product for new camps)
2. Auto-link Coach↔Spot via Camp (save_post_product)
3. Helper: ridemaster_find_relation()

Hooks:
- `save_post_product` priority 10 → init_new_camp()
- `save_post_product` priority 30 → auto_link_coach_to_spot()

#### class-auth.php — RM_Auth
Snippets integrated:
1. All login/redirect logic (6 template_redirect hooks + 2 login filters)
2. Guest photo upload REST endpoint + JS
3. Guest photo association after JFB insert
4. Bypass logout confirmation

Hooks:
- `template_redirect` → redirect_login_page(), redirect_register_page(), redirect_suspended_page(), redirect_my_account(), redirect_coach_dashboard()
- `woocommerce_login_redirect` → woo_login_redirect()
- `login_redirect` → wp_login_redirect()
- `rest_api_init` → register_guest_upload_endpoint()
- `wp_footer` → guest_upload_js()
- `jet-form-builder/action/after-post-insert` → associate_guest_photos()
- `init` → bypass_logout_confirmation()

#### class-admin.php — RM_Admin
Snippets integrated:
1. Coach Status Column (full admin UI: column, filter, sort, AJAX)
2. Auto-publish on "active" status (set_object_terms)
3. Media library restriction (coaches see only their uploads)

Hooks:
- `manage_coach_posts_columns` → add_status_column()
- `manage_coach_posts_custom_column` → render_status_column()
- `manage_edit-coach_sortable_columns` → sortable_status_column()
- `restrict_manage_posts` → status_filter_dropdown()
- `pre_get_posts` → apply_status_filter()
- `admin_footer-edit.php` → status_column_js_css()
- `wp_ajax_rm_update_coach_status` → ajax_update_status()
- `set_object_terms` → on_coach_status_change()
- `ajax_query_attachments_args` → restrict_media_library()

#### class-cleanup.php — RM_Cleanup
Snippets integrated:
1. Cascade deletion: User → Coach → Camps
2. Coach trash → camps trash
3. Coach delete → camps delete + user delete + relations cleanup
4. Camp delete → relations cleanup
5. Orphan Coach↔Spot link cleanup

Hooks:
- `delete_user` → on_delete_user()
- `wp_trash_post` → on_trash_coach()
- `before_delete_post` → on_delete_coach(), on_delete_camp()

#### class-inline-edit.php
- Existing RM_Inline_Edit class, moved as-is
- Only change: asset paths updated to use RM_PLUGIN_URL constant

---

## Plugin 2: ridemaster-ui-tweaks/

### File Structure

```
plugins/ridemaster-ui-tweaks/
└── ridemaster-ui-tweaks.php         (all-in-one)
```

### Contents (6 snippets)
1. Custom Quantity Selector +/- (CSS + JS)
2. "Book Now" button text (2 WooCommerce filters)
3. Hide WooCommerce info messages (CSS)
4. Camp creation form CSS (JetFormBuilder styling)
5. Flatpickr on create-camp page
6. JFB file upload preview fix (JS)

---

## Snippets to KEEP as Code Snippets (4)
- Date Range callback (format_date_range) — JetEngine/Elementor display
- Language flags shortcode ([language_flags]) — display utility
- Focus visible outline none — global CSS
- Force Elementor icons Safari — browser fix

---

## Migration Guide

After activating the new plugins:

### Snippets to DEACTIVATE in Code Snippets:
1. "Auto coach_post_id on init"
2. "Link coach post to user"
3. "Auto coach title from first/last name"
4. "Coach Dashboard Sidebar shortcodes"
5. "Coach profile URL shortcode"
6. "Override JFB preset post ID"
7. "Dashboard query (current_coach_post_id)"
8. "Camp creation initialization"
9. "Auto-link Coach↔Spot via Camp"
10. "Coach Status Column" → also deactivate the separate plugin `ridemaster-coach-status-column.php`
11. "Auto-publish on active status"
12. "Login/redirect logic"
13. "Guest photo upload"
14. "Media library restriction"
15. "Cascade deletion"
16. "Bypass logout confirmation"
17. "Custom Quantity Selector +/-"
18. "Book Now button text"
19. "Hide WooCommerce messages"
20. "Camp creation form CSS"
21. "Flatpickr on create-camp"
22. "JFB file upload preview fix"

### Snippets to KEEP ACTIVE:
- "Date Range callback (format_date_range)"
- "Language flags shortcode"
- "Focus visible outline none"
- "Force Elementor icons Safari"

---

## Implementation Tasks

### Task 1: Create plugin directory structure

### Task 2: Write ridemaster.php bootstrap

### Task 3: Write class-coach.php

### Task 4: Write class-camp.php

### Task 5: Write class-auth.php

### Task 6: Write class-admin.php

### Task 7: Write class-cleanup.php

### Task 8: Move inline-edit into unified plugin

### Task 9: Write ridemaster-ui-tweaks plugin

### Task 10: Final review & migration guide
