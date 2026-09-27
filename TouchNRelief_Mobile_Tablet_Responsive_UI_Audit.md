# TouchNRelief Mobile & Tablet Responsive UI Audit Guide

## Purpose

Use this checklist to audit and improve the **entire TouchNRelief
system** on phones and tablets. The goal is not only to make pages
technically responsive, but to ensure every screen is readable,
touch-friendly, correctly positioned, visually consistent, and free from
overlapping, clipping, unwanted horizontal scrolling, or broken layouts.

This guide should be applied to **every page, component, modal, form,
table, dashboard, authentication screen, customer page, staff page,
receptionist page, and admin page**.

------------------------------------------------------------------------

## 1. Core Responsive Requirements

Every page must:

-   Fit inside the viewport without unintended horizontal scrolling.
-   Never allow text, buttons, cards, images, icons, menus, tables, or
    form controls to overlap.
-   Never allow content to be hidden behind fixed headers, bottom
    navigation, floating buttons, or sidebars.
-   Use responsive widths instead of fixed desktop widths.
-   Keep appropriate spacing from the left and right edges of the
    screen.
-   Maintain readable font sizes without requiring browser zoom.
-   Allow long text, email addresses, IDs, statuses, and labels to wrap
    safely.
-   Prevent images and media from exceeding their containers.
-   Maintain clear visual hierarchy on small screens.
-   Keep primary actions easy to find and reach.
-   Avoid excessively large blank spaces.
-   Avoid excessively compressed content.
-   Preserve consistent alignment and spacing throughout the system.
-   Support portrait and landscape orientation where practical.

Recommended global safeguards:

``` css
*, *::before, *::after {
    box-sizing: border-box;
}

html, body {
    max-width: 100%;
    overflow-x: hidden;
}

img, video, svg {
    max-width: 100%;
    height: auto;
}

input, select, textarea, button {
    max-width: 100%;
}
```

Do not use `overflow-x: hidden` as a way to conceal genuinely broken
layouts. Fix the component causing overflow first.

------------------------------------------------------------------------

## 2. Required Viewport Testing

Do not optimize for only one phone. Test representative widths across
the supported range.

  Category                Suggested viewport
  ----------------------- --------------------
  Very small phone        320 × 568
  Small phone             360 × 640
  Common Android          360 × 800
  Medium phone            375 × 667
  Modern phone            390 × 844
  Large phone             412 × 915
  Large Android           430 × 932
  Small tablet portrait   600 × 960
  iPad Mini portrait      768 × 1024
  iPad portrait           820 × 1180
  iPad Air/Pro class      834 × 1194
  Large tablet portrait   1024 × 1366

Also test representative devices in **landscape orientation**.

Do not rely on device names alone. Responsive behavior should work
continuously between breakpoints.

Suggested layout ranges:

``` text
320–479px    Small/mobile
480–767px    Large mobile
768–1023px   Tablet
1024px+      Desktop / large tablet
```

------------------------------------------------------------------------

## 3. Mobile Layout Audit

For every page, inspect:

### Page container

-   No element extends beyond viewport width.
-   Content has consistent mobile padding.
-   Main content starts below the header.
-   Bottom content is not hidden behind fixed navigation.
-   Page height works with short and long content.
-   No unnecessary fixed heights cause clipping.
-   Desktop `min-width` rules are removed or overridden where necessary.

### Positioning

Check all uses of:

``` css
position: absolute;
position: fixed;
position: sticky;
transform: translate(...);
top:
right:
bottom:
left:
```

Confirm that positioned elements do not:

-   overlap text;
-   cover buttons;
-   leave the viewport;
-   obscure form fields;
-   cover navigation;
-   break when content becomes taller;
-   depend on desktop-only coordinates.

Prefer normal document flow, Flexbox, or CSS Grid where possible.

------------------------------------------------------------------------

## 4. Header and Navigation

On phones:

-   Logo fits without being cropped.
-   App/business name does not collide with icons.
-   Hamburger/menu button is clearly visible.
-   Header actions do not overlap.
-   Long usernames are truncated or wrapped appropriately.
-   Dropdowns stay inside the viewport.
-   Mobile navigation opens and closes reliably.
-   Menu items have sufficient touch height.
-   Active-page state remains visible.
-   Sidebar does not remain permanently open over content.
-   Opening a drawer should not create accidental horizontal scrolling.
-   Fixed/sticky headers must not cover page headings.

On tablets:

-   Decide intentionally whether tablet uses mobile navigation or
    desktop sidebar.
-   Sidebar width must leave enough usable content space.
-   Collapsed navigation icons remain understandable.
-   Navigation labels must not be cut off.

------------------------------------------------------------------------

## 5. Dashboard

Audit every dashboard card and widget:

-   Stat cards stack or form an appropriate responsive grid.
-   Cards never become too narrow for their content.
-   Numbers do not overflow.
-   Titles and subtitles wrap correctly.
-   Icons remain aligned.
-   Charts resize with their parent containers.
-   Chart legends remain readable.
-   Axis labels do not overlap.
-   Tooltips stay within the screen where possible.
-   Pie/doughnut charts do not become unreadably small.
-   Bar charts remain understandable without horizontal page overflow.
-   Dashboard filters stack on smaller widths.
-   Date filters and dropdowns remain usable.
-   No desktop-only empty spaces remain after stacking.

Example:

``` css
.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(100%, 240px), 1fr));
    gap: 1rem;
}
```

------------------------------------------------------------------------

## 6. Cards and Content Panels

Check:

-   Cards use `width: 100%` where appropriate.
-   Multiple desktop columns collapse logically.
-   Card padding decreases moderately on smaller screens.
-   Titles do not collide with action menus.
-   Badges and status indicators wrap properly.
-   Card footers remain aligned.
-   Images preserve aspect ratio.
-   Action buttons stack when there is insufficient horizontal room.
-   Very long content does not stretch the card beyond the viewport.

Avoid fixed card widths such as:

``` css
width: 500px;
```

Prefer:

``` css
width: 100%;
max-width: 500px;
```

where a maximum width is actually needed.

------------------------------------------------------------------------

## 7. Forms

Every form must be audited on mobile.

Check:

-   Labels remain associated with fields.
-   Inputs fill available width.
-   Input text is readable.
-   Placeholder text does not overflow.
-   Select elements fit.
-   Date/time inputs remain usable.
-   Textareas resize appropriately.
-   Validation messages wrap.
-   Error icons do not overlap input text.
-   Password visibility buttons remain correctly positioned.
-   Prefix/suffix icons do not cover typed values.
-   Radio buttons and checkboxes have touch-friendly labels.
-   Multi-column desktop forms collapse to one column when needed.
-   Submit and cancel actions remain visible.
-   Keyboard appearance does not make important controls inaccessible.

For iPhone/mobile browsers, use at least **16px** input text where
appropriate to avoid unwanted automatic zoom.

------------------------------------------------------------------------

## 8. Buttons and Touch Targets

Check every clickable control:

-   Buttons do not overlap.
-   Labels are not cut off.
-   Buttons wrap or stack when necessary.
-   Important buttons are not excessively small.
-   Icon-only buttons have clear meaning/accessibility labels.
-   Adequate spacing exists between adjacent controls.
-   Destructive actions are visually distinguishable.
-   Hover-only interactions also work through tap/focus.

Aim for touch targets around **44 × 44 CSS pixels** where practical.

------------------------------------------------------------------------

## 9. Tables

Desktop tables are a frequent mobile failure point.

For each table decide whether to:

1.  allow a contained horizontal scroll;
2.  convert rows into mobile cards;
3.  hide genuinely secondary columns; or
4.  provide a compact responsive representation.

If horizontal scrolling is appropriate:

``` css
.table-responsive {
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}
```

Check:

-   The table itself may scroll, but the whole page should not.
-   Important identifiers remain understandable.
-   Action buttons remain accessible.
-   Headers are clear.
-   Long emails/names do not destroy column widths.
-   Pagination fits.
-   Search and filter controls stack appropriately.

------------------------------------------------------------------------

## 10. Modals, Dialogs, Alerts and Toasts

Check all overlays:

-   Modal width never exceeds viewport.
-   Modal has safe margins on phones.
-   Tall modal content can scroll.
-   Close button remains visible.
-   Footer actions do not disappear below the viewport.
-   Keyboard does not permanently obscure fields/actions.
-   Confirmation dialogs remain readable.
-   Toasts do not extend off-screen.
-   Toasts do not cover essential navigation/actions.
-   Backdrop covers the entire screen.
-   Background page should not scroll unexpectedly while a blocking
    modal is open.

Example:

``` css
.modal-dialog {
    width: calc(100% - 2rem);
    max-width: 600px;
    margin-inline: auto;
}
```

------------------------------------------------------------------------

## 11. Authentication and Social Login

Audit:

-   Login
-   Registration
-   Forgot password
-   Reset password
-   Verification code
-   Google sign-in
-   Social-account completion form

Check:

-   Auth card fits narrow screens.
-   Logo and heading remain proportionate.
-   Social-login buttons remain full-width/readable.
-   Provider icons align correctly.
-   Divider text does not overlap its lines.
-   Long validation errors wrap.
-   Legal consent text is readable.
-   Terms/privacy links are easy to tap.
-   Contact number, birthday and sex fields stack appropriately.
-   Submit actions remain visible when the mobile keyboard opens.

------------------------------------------------------------------------

## 12. Appointment and Booking UI

Audit the complete customer booking journey:

-   Service selection
-   Therapist selection
-   Date picker
-   Time-slot selection
-   Booking summary
-   Payment selection
-   Confirmation
-   Appointment history/details
-   Cancellation/refund interfaces

Check:

-   Service cards stack correctly.
-   Prices remain aligned.
-   Therapist photos do not distort.
-   Calendar fits phone width.
-   Calendar navigation arrows remain accessible.
-   Day numbers do not overlap.
-   Disabled/available dates are visually distinguishable.
-   Time-slot buttons wrap into a usable grid.
-   Selected state is obvious.
-   Booking summary does not overflow.
-   Long service names wrap.
-   Payment buttons remain easy to tap.
-   Confirmation details remain readable without horizontal scrolling.

------------------------------------------------------------------------

## 13. Calendars and Date Pickers

Check:

-   Seven-day grid fits the viewport.
-   Header/month controls fit.
-   Previous/next buttons do not overlap month title.
-   Date cells remain tappable.
-   Events do not make cells overflow.
-   Mobile event details can be opened without hover.
-   Landscape orientation does not break layout.
-   Pop-up date pickers remain within viewport.

For complex staff calendars, consider switching to an agenda/list view
on narrow phones instead of forcing the desktop calendar into a tiny
width.

------------------------------------------------------------------------

## 14. Customer, Receptionist, Staff and Admin Interfaces

Test **each role independently** because different navigation and
actions may create different responsive problems.

### Customer

Check booking, appointments, payments, notifications, account/profile,
social login and legal pages.

### Receptionist

Check appointment management, customer lookup, booking controls,
payment/status controls, filters and tables.

### Therapist/Staff

Check assigned appointments, schedules, status actions, notifications
and profile.

### Admin

Check dashboard, user/staff management, services, appointments,
transactions, settings, reports and all management tables/forms.

Do not assume a shared template means every role is responsive.

------------------------------------------------------------------------

## 15. Images, Avatars and Icons

Check:

-   Profile images use controlled dimensions.
-   `object-fit: cover` is used when cropping is appropriate.
-   Images do not stretch.
-   Icons do not shrink unexpectedly.
-   SVGs remain inside containers.
-   Empty/broken-image states do not break layouts.
-   Large uploaded images do not dictate container width.
-   Avatar + long name combinations remain aligned.

------------------------------------------------------------------------

## 16. Text and Typography

Test unusually long realistic values.

Examples:

``` text
Very Long Customer Full Name That Needs To Wrap Correctly
verylongbusinessaccountemail@touchnrelief.app
A very long service description that occupies several lines.
```

Check:

-   No text overlaps adjacent elements.
-   Long words/URLs can break when required.
-   Headings scale appropriately.
-   Body text remains readable.
-   Line-height remains comfortable.
-   Status badges do not cut text.

Useful safeguard:

``` css
overflow-wrap: anywhere;
word-break: normal;
```

Apply thoughtfully rather than globally when it harms normal typography.

------------------------------------------------------------------------

## 17. Flexbox and Grid Audit

Common causes of mobile overflow include flex children refusing to
shrink.

Check relevant children for:

``` css
min-width: 0;
```

Use wrapping where appropriate:

``` css
.actions {
    display: flex;
    flex-wrap: wrap;
    gap: .75rem;
}
```

For grids, avoid fixed desktop definitions that survive on phones:

``` css
grid-template-columns: repeat(4, 300px);
```

Use responsive alternatives or media queries.

------------------------------------------------------------------------

## 18. Fixed Width and Height Audit

Search the project's CSS/Blade/components for:

``` text
width:
min-width:
max-width:
height:
min-height:
max-height:
100vw
position: absolute
position: fixed
white-space: nowrap
overflow:
grid-template-columns
```

Review every suspicious fixed dimension.

Pay special attention to:

-   `width: 100vw` inside containers;
-   large pixel widths;
-   `min-width` on tables/cards/forms;
-   fixed heights around dynamic text;
-   `white-space: nowrap`;
-   negative margins;
-   absolute positioning.

------------------------------------------------------------------------

## 19. Overlap Detection Checklist

At every test width verify there is no overlap between:

-   Header ↔ page content
-   Sidebar ↔ main content
-   Navigation ↔ logo
-   Text ↔ icons
-   Labels ↔ inputs
-   Input text ↔ trailing icons
-   Buttons ↔ buttons
-   Buttons ↔ text
-   Badges ↔ headings
-   Chart ↔ legend
-   Table ↔ page edge
-   Modal ↔ viewport
-   Toast ↔ navigation
-   Floating action button ↔ bottom navigation
-   Footer ↔ content
-   Calendar controls ↔ calendar title
-   Dropdown ↔ viewport edge
-   Profile image ↔ name/status
-   Fixed elements ↔ mobile keyboard

------------------------------------------------------------------------

## 20. Horizontal Overflow Debugging

If the page scrolls sideways, find the actual offending element.

Temporary development diagnostic:

``` css
/* DEVELOPMENT ONLY */
* {
    outline: 1px solid rgba(255, 0, 0, 0.15);
}
```

In DevTools console, developers can also inspect elements whose bounding
boxes exceed the document width.

Common causes:

-   fixed pixel widths;
-   oversized images;
-   tables;
-   `100vw` plus padding;
-   negative margins;
-   long unbreakable strings;
-   transformed elements;
-   absolute-positioned elements;
-   non-wrapping flex rows;
-   third-party widgets.

Do not consider the bug solved merely because horizontal overflow was
hidden.

------------------------------------------------------------------------

## 21. Mobile Spacing

Maintain a consistent spacing system.

Suggested starting values:

``` text
Page side padding:     16px
Card gap:              12–16px
Card padding:          16px
Form field gap:        12–16px
Section gap:           20–32px
Button gap:            8–12px
```

These are guidelines, not mandatory fixed values. Adjust based on the
existing TouchNRelief design.

------------------------------------------------------------------------

## 22. Safe Areas

For fixed mobile navigation or full-screen layouts, account for device
safe areas when applicable:

``` css
padding-bottom: env(safe-area-inset-bottom);
padding-top: env(safe-area-inset-top);
```

This is particularly relevant to modern iPhones and installed/PWA-style
layouts.

------------------------------------------------------------------------

## 23. Mobile Browser Testing

Test at least:

-   Chrome on Android
-   Safari on iPhone/iPad
-   Chrome responsive DevTools
-   Edge responsive DevTools

Where real devices are unavailable, browser emulation is useful, but
final critical flows should also be checked on physical devices when
possible.

Test:

-   scrolling;
-   tap behavior;
-   keyboard behavior;
-   autofill;
-   date/time controls;
-   select menus;
-   file uploads;
-   back navigation;
-   orientation changes;
-   sticky/fixed elements.

------------------------------------------------------------------------

## 24. Accessibility on Mobile

Responsive improvements must not reduce accessibility.

Check:

-   Sufficient text/background contrast.
-   Visible keyboard focus.
-   Form fields have labels.
-   Icons have accessible names when needed.
-   Buttons are actual interactive controls.
-   Touch targets are sufficiently large.
-   Content order remains logical after responsive rearrangement.
-   Zoom is not disabled.
-   Error messages identify the affected field.
-   Screen-reader labels do not disappear in mobile variants.

Do not use:

``` html
<meta name="viewport" content="user-scalable=no">
```

Allow users to zoom.

------------------------------------------------------------------------

## 25. Performance on Mobile

Check:

-   Images are appropriately sized/compressed.
-   Pages do not load unnecessarily large desktop assets.
-   Lazy-load suitable off-screen images.
-   Avoid excessive animation.
-   Avoid layout shifts while content loads.
-   Charts do not cause repeated resizing.
-   Mobile menu interaction remains smooth.
-   Loading indicators do not shift page structure dramatically.

------------------------------------------------------------------------

## 26. Responsive Behavior by Component

Use this as the expected behavior matrix.

  -----------------------------------------------------------------------
  Component               Phone                   Tablet
  ----------------------- ----------------------- -----------------------
  Sidebar                 Hidden/drawer           Drawer or compact
                                                  sidebar

  Dashboard cards         1 column, sometimes 2   2--3 columns
                          if space allows         

  Forms                   Primarily 1 column      1--2 columns

  Tables                  Responsive cards or     Contained table/scroll
                          contained scroll        

  Modal                   Near full width with    Centered constrained
                          margins                 width

  Action groups           Wrap/stack              Wrap/inline

  Charts                  Full-width              Full-width/grid

  Calendar                Compact/agenda where    Responsive calendar
                          needed                  

  Navigation              Mobile menu             Mobile/compact desktop

  Content padding         Reduced                 Moderate

  Typography              Mobile scale            Intermediate scale
  -----------------------------------------------------------------------

------------------------------------------------------------------------

## 27. Page-by-Page QA Template

Repeat this audit for **every route/page**.

``` text
Page:
Role:
URL/Route:

320px:  PASS / FAIL
360px:  PASS / FAIL
375px:  PASS / FAIL
390px:  PASS / FAIL
412px:  PASS / FAIL
430px:  PASS / FAIL
600px:  PASS / FAIL
768px:  PASS / FAIL
820px:  PASS / FAIL
834px:  PASS / FAIL
1024px: PASS / FAIL

Portrait:  PASS / FAIL
Landscape: PASS / FAIL

Horizontal overflow:       YES / NO
Overlapping elements:      YES / NO
Clipped content:           YES / NO
Navigation issue:          YES / NO
Form issue:                YES / NO
Table issue:               YES / NO
Modal issue:               YES / NO
Touch-target issue:        YES / NO
Typography issue:          YES / NO
Keyboard obstruction:      YES / NO
Fixed/sticky overlap:      YES / NO

Problems found:
1.
2.
3.

Fixes applied:
1.
2.
3.

Retested:
PASS / FAIL
```

------------------------------------------------------------------------

## 28. Full-System Responsive Implementation Task

The developer should perform the following process:

1.  Inventory all Laravel routes and corresponding
    Blade/layout/component files.
2.  Identify all shared layouts, navigation components and CSS files.
3.  Audit global responsive behavior before fixing individual pages.
4.  Search for fixed widths, problematic minimum widths, absolute
    positioning and non-wrapping content.
5.  Fix header/sidebar/mobile navigation first.
6.  Fix global containers and spacing.
7.  Fix dashboards and card grids.
8.  Fix all forms.
9.  Fix tables and management lists.
10. Fix calendars and booking interfaces.
11. Fix modals, dropdowns, notifications and overlays.
12. Audit authentication/social-login screens.
13. Audit each role separately.
14. Test all specified viewport widths.
15. Test portrait and landscape.
16. Test long/dynamic content.
17. Test empty, loading, validation-error and populated states.
18. Test mobile keyboard interaction.
19. Re-run application tests after UI changes.
20. Perform a final visual regression pass on desktop to ensure mobile
    fixes did not break desktop layouts.

------------------------------------------------------------------------

## 29. Definition of Done

A page is **not finished** simply because it opens on a phone.

A page passes only when:

-   There is no unintended page-level horizontal scrolling.
-   There are no overlapping elements.
-   There is no clipped essential content.
-   No important action is inaccessible.
-   Navigation works correctly.
-   Forms are comfortable to complete.
-   Tables/data remain understandable.
-   Modals and dropdowns stay usable.
-   Text is readable.
-   Touch targets are usable.
-   Images/icons remain proportionate.
-   Charts/calendars remain understandable.
-   Mobile keyboard interaction is usable.
-   Both portrait and landscape have been checked where relevant.
-   Tablet layout is intentionally designed, not merely a stretched
    phone layout.
-   Desktop behavior remains correct after the changes.

------------------------------------------------------------------------

## 30. Final Instruction for TouchNRelief

Apply this responsive audit to the **whole TouchNRelief system**, not
only the currently visible page.

Do not remove existing functionality merely to make a page fit. Preserve
Laravel logic, routes, validation, authorization, payments, bookings,
authentication, social login, database behavior and role permissions.

Prioritize fixes in this order:

``` text
1. Broken/overlapping content
2. Horizontal overflow
3. Hidden or inaccessible actions
4. Navigation problems
5. Form usability
6. Tables and data presentation
7. Modal/dropdown/calendar behavior
8. Touch accessibility
9. Spacing and typography
10. Visual polish and consistency
```

After every responsive change, verify both the affected mobile layouts
**and the existing desktop layout**.

The final result should feel intentionally designed for mobile and
tablet---not simply a desktop interface squeezed into a smaller screen.

------------------------------------------------------------------------

## 31. Implemented Audit Record — September 27, 2026

### Scope and method

The responsive audit was executed against the locally rendered Laravel
application in headless Chrome. Predictable local accounts were used for
the customer, receptionist, and administrator roles, including a customer
with an unusually long full name. Temporary audit accounts were removed
after testing.

The automated pass measured the rendered document width and visible
element bounds after page scripts completed. An element inside an
intentional horizontal scroller, such as a wide staff data table, was
distinguished from page-level overflow. Representative screenshots were
also reviewed at phone, tablet, and landscape sizes.

### Viewports tested

- 320 × 568
- 360 × 640
- 360 × 800
- 375 × 667
- 390 × 844
- 412 × 915
- 430 × 932
- 600 × 960
- 768 × 1024
- 820 × 1180
- 834 × 1194
- 1024 × 1366
- 667 × 375 landscape
- 1024 × 768 landscape
- 1440 × 900 desktop regression

### Routes tested

**Guest/public**

- `/`
- `/login`
- `/forgot-password`
- `/privacy-policy`
- `/terms-and-conditions`
- `/data-deletion`

**Customer**

- `/`
- `/booking`
- `/profile`

**Receptionist**

- `/receptionist-dashboard`
- `/appointments`
- `/client-records`
- `/completed-sessions`
- `/ongoing-sessions`
- `/services`
- `/therapist-tracking`

**Administrator**

- `/dashboard`
- `/users`
- `/appointments`
- `/client-records`
- `/services`
- `/therapist-tracking`
- `/reporting`
- `/activity-log`
- `/landing-settings`

### Problems found

1. Staff pages at 768–1024 px retained the compact desktop sidebar while
   headers and several page layouts still required desktop widths. This
   created 100–250 px of page-level overflow on appointments, client
   records, services, user management, and therapist monitoring.
2. Text inputs on authentication, booking, customer profile, and staff
   forms rendered below 16 px at phone widths, which can trigger automatic
   browser zoom when a field receives focus.
3. The chatbot suggestion row exposed a browser scrollbar on narrow
   screens, adding visual clutter beneath the suggestion chips.
4. The audit guide still listed Facebook sign-in after Facebook
   authentication was removed from the application.

### Fixes applied

1. Extended the existing staff responsive layout through 1024 px. Tablets
   now use the compact top navigation, accessible menu, responsive content
   grids, and fixed staff shortcuts instead of squeezing the desktop
   sidebar beside the content.
2. Extended page-specific appointment, ongoing-session, receptionist, and
   therapist-monitoring responsive rules through tablet width.
3. Set text-entry controls to at least 16 px at responsive widths while
   preserving checkbox and radio sizing.
4. Kept chatbot suggestion chips horizontally swipeable while hiding the
   visual scrollbar.
5. Updated the authentication audit scope to reflect Google-only social
   sign-in.

### Retest result

- 350 phone and tablet route/viewport combinations: **PASS**
- 25 desktop route checks at 1440 × 900: **PASS**
- Page-level horizontal overflow after fixes: **0 occurrences**
- Public mobile navigation: **PASS**
- Customer chatbot panel: **PASS**
- Customer appointments modal: **PASS**
- Staff mobile navigation: **PASS**
- Staff notifications panel: **PASS**
- Add-appointment dialog: **PASS**
- Long customer name wrapping: **PASS**
- Phone input zoom safeguard for text-entry controls: **PASS**

### Manual device follow-up

The rendered browser audit cannot reproduce every operating-system
behavior. Before a high-stakes release or defense demonstration, perform a
short physical-device smoke test for iOS Safari keyboard resizing,
Android autofill, native date/select controls, file uploads, and payment
provider handoff/return behavior.
