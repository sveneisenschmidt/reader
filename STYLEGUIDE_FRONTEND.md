# Frontend Styleguide

## Principles

1. **IDs for unique pages** - Each page gets a unique ID on `<main>`
2. **Reusable CSS components** - Shared styles in `shared-*.css` files
3. **Data attributes for JS hooks** - Use `data-*` attributes for JavaScript, not for styling
4. **Page-specific CSS for overrides** - Only page-specific styles in `page-*.css` files

## CSS Architecture

### File Structure

```
assets/css/
├── shared-base.css          # Variables, reset, typography, global elements
├── shared-layout.css        # Reusable layout components (.page-container, .page-section)
├── shared-forms.css         # Reusable form components (.form, .form-row, .form-actions)
├── page-auth.css            # Auth pages (login, setup) specific styles
├── page-feed.css            # Feed page specific styles
├── page-preferences.css     # Preferences page specific styles
├── page-subscriptions.css   # Subscriptions page specific styles
└── page-onboarding.css      # Onboarding page specific styles
```

### Naming Convention

- `shared-*.css` - Framework/shared styles, loaded on all or multiple pages
- `page-*.css` - Page-specific styles, loaded only on that page

### Loading Order

For pages using shared components:
```html
<link rel="stylesheet" href="{{ asset('css/shared-base.css') }}">
<link rel="stylesheet" href="{{ asset('css/shared-layout.css') }}">
<link rel="stylesheet" href="{{ asset('css/shared-forms.css') }}">
<link rel="stylesheet" href="{{ asset('css/page-[name].css') }}">
```

## Reusable Components

### Layout Components (shared-layout.css)

| Class | Description |
|-------|-------------|
| `.page-container` | Centered page layout with flex column, padding, overflow |
| `.page-content` | Constrained width wrapper (max-width: --width-form) |
| `.page-section` | Section with heading (h2 with border-bottom) |
| `.page-section--bordered` | Section with top border separator |
| `.flash-success` | Success flash message |
| `.flash-error` | Error flash message |

### Form Components (shared-forms.css)

| Class | Description |
|-------|-------------|
| `.form` | Form wrapper with max-width |
| `.form-row` | Single field container with margin |
| `.form-actions` | Button container with flex |
| `.form-actions--right` | Right-aligned buttons |
| `.form-inline` | Input + button side by side |
| `.form-checkbox` | Checkbox with label and description |
| `.form-error` | Error message text |
| `.form-errors` | Error list |
| `.form-links` | Vertical link list |
| `.form-hint` | Helper text for forms |

### Button Classes (shared-base.css)

| Class | Description |
|-------|-------------|
| `.btn-link` | Button styled as a link (no background/border) |
| `.btn-secondary` | Secondary button with border |

### Status Indicators

| Class | Description |
|-------|-------------|
| `.status-online` | Green status indicator |
| `.status-offline` | Red status indicator |

## Page IDs

| Page | ID | Description |
|------|-----|-------------|
| Login | `#login` | Login form |
| Setup | `#setup` | Initial setup with TOTP |
| Feed | `#feed` | Main view with feed list |
| Subscriptions | `#subscriptions` | Manage subscriptions |
| Preferences | `#preferences` | User preferences |
| Onboarding | `#onboarding` | Add first feed |

## Template Blocks

### Base Template (base.html.twig)

```twig
{% block preload %}{% endblock %}      {# Preload hints for CSS #}
{% block stylesheets %}{% endblock %}  {# Additional stylesheets #}
{% block page_id %}{% endblock %}      {# ID for <main> element #}
{% block page_class %}{% endblock %}   {# Class for <main> element #}
{% block main %}{% endblock %}         {# Page content #}
```

### Using Layout Components

```twig
{% block page_id %}preferences{% endblock %}
{% block page_class %} class="page-container"{% endblock %}

{% block main %}
    {% for message in app.flashes('success') %}
        <p class="flash-success">{{ message }}</p>
    {% endfor %}

    {{ form_start(form, {'attr': {'class': 'form'}}) }}
    <section class="page-section">
        <h2>Section Title</h2>
        <div class="form-row">
            {{ form_row(form.field) }}
        </div>
        <div class="form-actions">
            {{ form_widget(form.save) }}
        </div>
    </section>
    {{ form_end(form) }}
{% endblock %}
```

## Page Structures

### Form Pages (Preferences, Subscriptions, Onboarding)

```
main#[page-id].page-container
├── p.flash-success (optional)
└── form.form
    └── section.page-section
        ├── h2 (Section title)
        ├── div.form-row (Fields)
        └── div.form-actions (Buttons)
```

### Auth Pages (Login, Setup)

```
main#[page-id].page-container
├── p (Intro text)
├── p[role="alert"] (Error, optional)
└── form.form
    ├── div.form-row (Fields)
    └── button (Submit)
```

### Feed Page

```
main#feed
├── aside[data-sidebar]
│   └── ul.subscription-list > li > a
├── section[data-reading-list]
│   └── div.feed-item > a
└── article[data-reading-pane]
    ├── header (Title, meta)
    ├── div.content (Article content)
    └── footer (Actions)
```

## Allowed Classes

### Layout (from shared-layout.css)
- `.page-container` - Centered flex container
- `.page-section` - Section with heading
- `.page-section--bordered` - Section with top border
- `.flash-success`, `.flash-error` - Flash messages

### Forms (from shared-forms.css)
- `.form` - Form wrapper
- `.form-row` - Field container
- `.form-actions` - Button container
- `.form-inline` - Horizontal input group
- `.form-checkbox` - Checkbox layout
- `.form-error`, `.form-errors` - Errors
- `.form-links` - Link list
- `.form-hint` - Helper text

### Buttons (from shared-base.css)
- `.btn-link` - Button as link
- `.btn-secondary` - Secondary button

### States
- `.active` - Active element
- `.read` - Read item

### Other
- `.otp-input` - OTP input field
- `.inline` - Inline display

## Forbidden Patterns

- No `.form-group`, `.form-control`, `.container` wrappers
- No BEM notation (`.block__element--modifier`)
- No utility classes (`.mt-4`, `.flex`, `.text-center`)
- No deeply nested classes (`.auth-page .auth-container .auth-form`)
- No `@import` in CSS (Symfony Asset Mapper doesn't resolve them)

## CSS Custom Properties

All variables defined in `:root` (shared-base.css):

### Colors
- `--color-text` - Text color
- `--color-link` - Link color
- `--color-border` - Border color
- `--color-bg` - Background color
- `--color-bg-muted` - Muted background
- `--color-success` - Success/online color
- `--color-error` - Error/offline color
- `--color-new` - New items indicator

### Button Colors
- `--button-primary-bg`, `--button-primary-text`
- `--button-primary-hover-bg`, `--button-primary-hover-text`
- `--button-secondary-bg`, `--button-secondary-text`, `--button-secondary-border`
- `--button-secondary-hover-text`, `--button-secondary-hover-border`

### Spacing
- `--spacing-xs` - 0.25rem
- `--spacing-sm` - 0.5rem
- `--spacing-button` - 0.75rem
- `--spacing-md` - 1rem
- `--spacing-lg` - 1.5rem
- `--spacing-xl` - 2rem

### Sizes
- `--width-form` - Form max-width (480px)
- `--width-button` - Button width (7rem)
- `--size-otp-input` - OTP input field
- `--size-qr-code` - QR code size

## Examples

### Good: Using Shared Components
```twig
<main id="preferences" class="page-container">
    <form class="form">
        <section class="page-section">
            <h2>Profile</h2>
            <div class="form-row">
                {{ form_row(form.username) }}
            </div>
            <div class="form-actions">
                <button type="submit">Save</button>
            </div>
        </section>
    </form>
</main>
```

### Good: Page-Specific Override
```css
/* page-preferences.css */
#preferences {
    & .section-logout {
        display: flex;
        justify-content: flex-start;
    }
}
```

### Bad: Duplicating Shared Styles
```css
/* DON'T do this - use .page-container instead */
#preferences {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: var(--spacing-xl);
}
```
