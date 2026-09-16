---
name: Modern Productivity Workspace
colors:
  surface: '#f8f9ff'
  surface-dim: '#cbdbf5'
  surface-bright: '#f8f9ff'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#eff4ff'
  surface-container: '#e5eeff'
  surface-container-high: '#dce9ff'
  surface-container-highest: '#d3e4fe'
  on-surface: '#0b1c30'
  on-surface-variant: '#464555'
  inverse-surface: '#213145'
  inverse-on-surface: '#eaf1ff'
  outline: '#777587'
  outline-variant: '#c7c4d8'
  surface-tint: '#4d44e3'
  primary: '#3525cd'
  on-primary: '#ffffff'
  primary-container: '#4f46e5'
  on-primary-container: '#dad7ff'
  inverse-primary: '#c3c0ff'
  secondary: '#006591'
  on-secondary: '#ffffff'
  secondary-container: '#39b8fd'
  on-secondary-container: '#004666'
  tertiary: '#005338'
  on-tertiary: '#ffffff'
  tertiary-container: '#006e4b'
  on-tertiary-container: '#67f4b7'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#e2dfff'
  primary-fixed-dim: '#c3c0ff'
  on-primary-fixed: '#0f0069'
  on-primary-fixed-variant: '#3323cc'
  secondary-fixed: '#c9e6ff'
  secondary-fixed-dim: '#89ceff'
  on-secondary-fixed: '#001e2f'
  on-secondary-fixed-variant: '#004c6e'
  tertiary-fixed: '#6ffbbe'
  tertiary-fixed-dim: '#4edea3'
  on-tertiary-fixed: '#002113'
  on-tertiary-fixed-variant: '#005236'
  background: '#f8f9ff'
  on-background: '#0b1c30'
  surface-variant: '#d3e4fe'
typography:
  display:
    fontFamily: Inter
    fontSize: 36px
    fontWeight: '700'
    lineHeight: 44px
    letterSpacing: -0.025em
  headline-lg:
    fontFamily: Inter
    fontSize: 28px
    fontWeight: '600'
    lineHeight: 36px
    letterSpacing: -0.02em
  headline-lg-mobile:
    fontFamily: Inter
    fontSize: 22px
    fontWeight: '600'
    lineHeight: 28px
    letterSpacing: -0.015em
  headline-md:
    fontFamily: Inter
    fontSize: 20px
    fontWeight: '600'
    lineHeight: 28px
    letterSpacing: -0.015em
  headline-sm:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '600'
    lineHeight: 24px
    letterSpacing: -0.01em
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
    fontSize: 12px
    fontWeight: '400'
    lineHeight: 16px
  label-md:
    fontFamily: Inter
    fontSize: 13px
    fontWeight: '500'
    lineHeight: 18px
  label-sm:
    fontFamily: Inter
    fontSize: 11px
    fontWeight: '600'
    lineHeight: 14px
    letterSpacing: 0.02em
  code-sm:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: '500'
    lineHeight: 16px
rounded:
  sm: 0.25rem
  DEFAULT: 0.5rem
  md: 0.75rem
  lg: 1rem
  xl: 1.5rem
  full: 9999px
spacing:
  gutter: 1.5rem
  gutter-sm: 1rem
  margin: 2rem
  margin-mobile: 1rem
  space-xs: 0.25rem
  space-sm: 0.5rem
  space-md: 1rem
  space-lg: 1.5rem
  space-xl: 2rem
  space-2xl: 3rem
---

## Brand & Style

The design system establishes a high-performance productivity environment tailored for professionals, knowledge workers, and builders who require clear cognitive organization without visual noise. The brand tone is systematic, focused, and quietly confident. 

The interface combines a contemporary corporate SaaS discipline with precise, low-friction utilitarianism:
- **Tone:** Measured, distraction-free, structured, and intentional.
- **Visual Stance:** Ultra-clean light mode built upon a neutral slate canvas, pure white card planes, razor-thin structured outlines, and crisp indigo accents.
- **Cognitive Ergonomics:** High data density balanced by generous element padding and strict vertical rhythms, allowing users to parse timelines, metrics, and logs effortlessly.

## Colors

The palette is anchored by a cool, neutral slate canvas that recedes into the background, providing high contrast for white surface layers and deliberate color coding.

### Core Roles
- **Primary (`#4F46E5` - Indigo):** Reserved for primary interactive triggers, active navigation markers, selection states, and focus accents.
- **Secondary (`#0EA5E9` - Sky/Cyan):** Assigned to informational alerts, secondary metrics, and learning pathways.
- **Tertiary (`#10B981` - Emerald):** Applied to progress, health states, positive performance indicators, and completed actions.
- **Neutral (`#64748B` - Slate):** Anchors secondary content, structural borders (`#E2E8F0`), muted text, and canvas backgrounds (`#F8FAFC`).

### Semantic Activity Mapping
Activity categories use dedicated chromatic tokens paired with low-saturation, 10% opacity tints for background tags to maintain strict accessibility without visual competition:
- **Trabajo (Work):** Core Indigo (`#4F46E5`), background `#EEF2FF`.
- **Salud & Fitness:** Emerald (`#059669`), background `#ECFDF5`.
- **Hábitos (Habits):** Amber (`#D97706`), background `#FFFBEB`.
- **Finanzas (Finance):** Violet (`#7C3AED`), background `#F5F3FF`.
- **Aprendizaje (Learning):** Cyan (`#0891B2`), background `#ECFEFF`.

## Typography

Typography relies exclusively on Inter, configured with tabular numerals (`tnum`) for all numerical dashboards, timestamps, and metric trackers.

- **Weight Discipline:** Restrict usage to Regular (400) for narrative and data logs, Medium (500) for controls and table metadata, Semibold (600) for card titles and category headers, and Bold (700) exclusively for top-level summaries and KPI values.
- **Micro-Tracking:** Apply negative tracking (`-0.01em` to `-0.025em`) to all headings above 16px to maintain tight, editorial glyph cohesion. Badges and micro-labels utilize a slight positive tracking (`0.02em`) with uppercase or capitalized styling to ensure legibility at scale.

## Layout & Spacing

The layout is built on a responsive 12-column grid system that guarantees structural consistency across dense data views.

### Structure & Rhythm
- **Desktop (1024px+):** 12 columns, `1.5rem` (`24px`) gutters, `2rem` (`32px`) canvas margin. The layout uses a fixed left rail (260px) alongside a flexible analytical canvas.
- **Tablet (768px - 1023px):** 8 columns, `1rem` (`16px`) gutters, `1.5rem` (`24px`) outer margin. The left rail collapses into a compact icon bar or sliding panel.
- **Mobile (< 768px):** 4 columns, `1rem` (`16px`) gutters, `1rem` (`16px`) margin. Dashboard components collapse into a single stacked column where multi-metric summaries transform into horizontal swipe lanes.

Use the `8px` baseline grid for all margins, gap configurations, and container boundaries.

## Elevation & Depth

Visual hierarchy is achieved through a combination of crisp container boundaries and low-diffusion ambient drop shadows rather than heavy layering.

### Surface Hierarchy
1. **Canvas Level (Ground):** Slate tinted background (`#F8FAFC`).
2. **Surface Level (Containers):** Pure white cards (`#FFFFFF`) framed with a subtle 1px border (`#E2E8F0`).
3. **Elevated Surfaces (Dropdowns, Modals, Popovers):** Pure white (`#FFFFFF`) with a dual-shadow treatment:
   - Primary: `0 4px 6px -1px rgba(15, 23, 42, 0.06)`
   - Diffuse: `0 10px 15px -3px rgba(15, 23, 42, 0.08)`
   - Border: 1px solid `#CBD5E1`
4. **Interactive Hover States:** Subtle upward shadow translation (`0 4px 12px rgba(79, 70, 229, 0.08)`) with border transition to `#CBD5E1` or Indigo tinted borders.

## Shapes

The design uses a balanced `roundedness: 2` (base `0.5rem` / `8px`), balancing contemporary software warmth with industrial geometry.

### Radius Application
- **Base (8px / `0.5rem`):** Applied to buttons, inputs, category badges, dropdown menus, table headers, and timeline chips.
- **Large (16px / `1rem`):** Applied to primary content cards, analytics widgets, calendar log blocks, and modal overlays.
- **Small (4px / `0.25rem`):** Applied to internal progress bars, status indicators, nested metadata tags, and metric indicators.
- **Circular (Pill / 9999px):** Strictly reserved for numerical avatar markers, counter badges, and toggle switches.

## Components

### Buttons
- **Primary:** Solid indigo (`#4F46E5`), text `#FFFFFF`, font weight 500, height 40px, padding `0 1rem`, radius 8px. Hover state deepens to `#4338CA`. Active state scales subtly (`0.98`).
- **Secondary / Outline:** Background `#FFFFFF`, border 1px solid `#E2E8F0`, text `#1E293B`. Hover state applies `#F8FAFC` background and `#CBD5E1` border.
- **Ghost:** Background transparent, text `#64748B`. Hover state applies `#F1F5F9` background and `#0F172A` text.

### Category Chips & Badges
- Built using `label-sm` typography (11px, weight 600) with a 6px horizontal padding and 2px vertical padding.
- Displays a leading 6px solid dot indicating the category tone, followed by the title text.
- Standardizes background fills to 10% opacity tints of the source color with a matching 20% opacity border for contrast against white card surfaces.

### Cards & Analytical Widgets
- Pure white background (`#FFFFFF`), 1px solid `#E2E8F0` border, radius 16px, inner padding `1.5rem`.
- Card headers feature title typography (`headline-sm`), optional subtitle (`body-sm`), and a trailing right-aligned control or badge group.
- Data divider lines within cards use `#F1F5F9` to separate distinct timeline chunks or table rows without breaking the container flow.

### Inputs & Controls
- **Form Inputs:** 40px height, background `#FFFFFF`, border 1px solid `#CBD5E1`, radius 8px, padding `0 0.75rem`. Focus ring features an offset-free 2px glow: `box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.2)` with a `#4F46E5` border.
- **Checkboxes & Radios:** 18px dimensions, 1.5px border `#CBD5E1`, 4px radius (checkbox) or full circle (radio). Selected state fills with `#4F46E5` with a white iconography check mark.

### Daily Timeline & Activity Logs
- Vertical connector lines using a 2px stroke in `#E2E8F0`.
- Category time blocks utilize soft left-accent borders (3px solid in the respective category color) to anchor the card without overwhelming the general UI balance.
- Monospaced numerical timestamps (`tnum`) aligned left at 12px muted slate (`#64748B`).