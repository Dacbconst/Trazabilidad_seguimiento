---
name: Indigo Desktop Narrative
colors:
  surface: '#f7f9fb'
  surface-dim: '#d8dadc'
  surface-bright: '#f7f9fb'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#f2f4f6'
  surface-container: '#eceef0'
  surface-container-high: '#e6e8ea'
  surface-container-highest: '#e0e3e5'
  on-surface: '#191c1e'
  on-surface-variant: '#454652'
  inverse-surface: '#2d3133'
  inverse-on-surface: '#eff1f3'
  outline: '#757684'
  outline-variant: '#c5c5d4'
  surface-tint: '#4355b9'
  primary: '#24389c'
  on-primary: '#ffffff'
  primary-container: '#3f51b5'
  on-primary-container: '#cacfff'
  inverse-primary: '#bac3ff'
  secondary: '#515f74'
  on-secondary: '#ffffff'
  secondary-container: '#d5e3fc'
  on-secondary-container: '#57657a'
  tertiary: '#6c3400'
  on-tertiary: '#ffffff'
  tertiary-container: '#8f4700'
  on-tertiary-container: '#ffc7a2'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#dee0ff'
  primary-fixed-dim: '#bac3ff'
  on-primary-fixed: '#00105c'
  on-primary-fixed-variant: '#293ca0'
  secondary-fixed: '#d5e3fc'
  secondary-fixed-dim: '#b9c7df'
  on-secondary-fixed: '#0d1c2e'
  on-secondary-fixed-variant: '#3a485b'
  tertiary-fixed: '#ffdcc6'
  tertiary-fixed-dim: '#ffb784'
  on-tertiary-fixed: '#301400'
  on-tertiary-fixed-variant: '#713700'
  background: '#f7f9fb'
  on-background: '#191c1e'
  surface-variant: '#e0e3e5'
typography:
  display-lg:
    fontFamily: Inter
    fontSize: 32px
    fontWeight: '700'
    lineHeight: 40px
    letterSpacing: -0.02em
  headline-md:
    fontFamily: Inter
    fontSize: 24px
    fontWeight: '600'
    lineHeight: 32px
    letterSpacing: -0.01em
  headline-sm:
    fontFamily: Inter
    fontSize: 20px
    fontWeight: '600'
    lineHeight: 28px
  title-md:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '600'
    lineHeight: 24px
  body-lg:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 24px
  body-md:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '400'
    lineHeight: 20px
  body-sm:
    fontFamily: Inter
    fontSize: 13px
    fontWeight: '400'
    lineHeight: 18px
  label-md:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: '600'
    lineHeight: 16px
    letterSpacing: 0.05em
  label-sm:
    fontFamily: Inter
    fontSize: 11px
    fontWeight: '500'
    lineHeight: 14px
rounded:
  sm: 0.125rem
  DEFAULT: 0.25rem
  md: 0.375rem
  lg: 0.5rem
  xl: 0.75rem
  full: 9999px
spacing:
  base: 4px
  xs: 4px
  sm: 8px
  md: 16px
  lg: 24px
  xl: 32px
  gutter: 24px
  sidebar_width: 260px
  max_width: 1440px
---

## Brand & Style

The design system is engineered for high-density desktop environments where clarity and professional reliability are paramount. It targets enterprise users and power users who require a focused, "noise-free" workspace.

The visual style is **Corporate / Modern**, leaning heavily into a refined "Safe" identity. It prioritizes function over flair, utilizing a crisp white-label foundation with strategic indigo accents to guide the user's eye. The emotional response is one of competence and stability, achieved through rhythmic spacing, intentional information density, and a rejection of unnecessary decorative elements.

## Colors

The palette is anchored by **Deep Indigo (#3F51B5)**, used specifically for primary actions, active states, and critical brand touchpoints. Complementing this is **Slate Gray (#475569)**, which provides a neutral, sophisticated tone for secondary text and iconography.

The background uses a pure white canvas to maximize contrast. For structural separation, we employ a hierarchy of slate-tinted grays to define sidebars, header regions, and container backgrounds. High-saturation colors are reserved strictly for semantic feedback (Success, Warning, Error), ensuring that color always conveys meaning rather than mere decoration.

## Typography

This design system utilizes **Inter** exclusively to ensure maximum legibility across different pixel densities. The typographic scale is built on a 4px baseline grid to maintain vertical rhythm.

- **Headlines:** Use tighter letter spacing and semi-bold weights to create a strong visual anchor for page sections.
- **Body Text:** Standard body text is set at 14px for optimal desktop density. 16px is reserved for long-form reading or empty-state descriptions.
- **Labels:** Small, all-caps labels with increased tracking are used for metadata, table headers, and category tags to differentiate them from actionable body text.

## Layout & Spacing

The layout follows a **Fixed-Fluid Hybrid** model. Navigation and sidebars are fixed-width, while the main content area expands to a maximum width of 1440px, centering itself on larger displays to prevent line-lengths from becoming unreadable.

- **Grid:** A 12-column grid is used within the main content container.
- **Density:** The system defaults to "Comfortable" density (16px/24px padding), but components should be built to support a "Compact" mode for data-heavy dashboard views.
- **Rhythm:** All margins and paddings must be multiples of 4px. Use 24px (lg) for major section spacing and 8px (sm) for internal component spacing.

## Elevation & Depth

Hierarchy is established through **Ambient Shadows** and tonal layering. This design system avoids harsh borders in favor of soft depth cues.

- **Level 0 (Canvas):** Background color (#FFFFFF or #F8FAFC). No shadow.
- **Level 1 (Cards/Modules):** Used for standard dashboard widgets. Features a subtle, highly-diffused shadow: `0px 1px 3px rgba(0, 0, 0, 0.05), 0px 1px 2px rgba(0, 0, 0, 0.03)`.
- **Level 2 (Dropdowns/Popovers):** Used for interactive elements that sit above the UI. Features a more pronounced shadow: `0px 10px 15px -3px rgba(0, 0, 0, 0.1)`.
- **Contrast Outlines:** In addition to shadows, Level 1 surfaces use a 1px border in a very light slate (#E2E8F0) to ensure definition on pure white backgrounds.

## Shapes

The shape language is understated and professional. 
- **Standard (4px):** Applied to buttons, input fields, and checkboxes. This creates a crisp, structured look.
- **Large (8px):** Applied to cards, notification modules, and modals to soften the overall interface and make container boundaries clear.
- **Full (Pill):** Reserved exclusively for status tags (Chips) and search bars to distinguish them from primary action buttons.

## Components

### Buttons
Primary buttons use the Indigo seed color with white text. Secondary buttons use a Slate Gray outline with no fill. Ghost buttons are reserved for tertiary actions in headers.

### Notification Modules
The design system requires two distinct styles for system awareness:
- **Activity Feed:** High-density list items with 40px circular avatars or icons. Text is primary body-sm. Separation is achieved with subtle 1px bottom borders.
- **System Alerts:** Boxed modules with a thick left-accent border (4px) colored by severity (Indigo for Info, Red for Error). These use a light indigo or gray tinted background to stand out from the white canvas.

### Input Fields
Fields use a 1px Slate Gray border that shifts to Indigo on focus. Labels are always positioned above the field in `label-md` style.

### Cards
Dashboard cards should have a consistent 24px internal padding and a dedicated "Header" area with a bottom border to house the title and contextual actions (e.g., "View All").