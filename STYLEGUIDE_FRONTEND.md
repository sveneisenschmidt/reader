# Frontend Styleguide

## Principles

- Page ID on `<main>` element
- Shared styles in `shared-*.css`, page styles in `page-*.css`
- `data-*` attributes for JS, not styling
- No BEM, no utility classes, no `@import`

## CSS Files

Files in `assets/css/`:
- `shared-base.css` - Variables, reset, typography
- `shared-layout.css` - .page-container, .page-section, .flash-*
- `shared-forms.css` - .form, .form-row, .form-actions, etc.
- `page-auth.css`, `page-feed.css`, `page-preferences.css`, `page-subscriptions.css`, `page-onboarding.css`

Load order: shared-base → shared-layout → shared-forms → page-*

## Classes

### Layout
- `.page-container` - centered flex container
- `.page-section` - section with h2 border
- `.flash-success`, `.flash-error`

### Forms
- `.form` - form wrapper
- `.form-row` - field container
- `.form-actions` - button row
- `.form-inline` - input + button horizontal
- `.form-checkbox` - checkbox with label
- `.form-error`, `.form-errors`
- `.form-links`

### Buttons
- `.btn-link` - looks like link
- `.btn-secondary` - bordered button

### States
- `.active`, `.read`

## Page IDs

`#login`, `#setup`, `#feed`, `#subscriptions`, `#preferences`, `#onboarding`

## Template Blocks

```twig
{% block preload %}{% endblock %}
{% block stylesheets %}{% endblock %}
{% block page_id %}{% endblock %}
{% block page_class %}{% endblock %}
{% block main %}{% endblock %}
```

## Structure

Form pages: `main#[id].page-container` contains `form.form` with `section.page-section` holding `h2`, `div.form-row`, and `div.form-actions`.

Feed page: `main#feed` contains `aside[data-sidebar]`, `section[data-reading-list]`, and `article[data-reading-pane]`.

## CSS Variables

Colors: `--color-text`, `--color-link`, `--color-border`, `--color-bg`, `--color-bg-muted`, `--color-success`, `--color-error`, `--color-new`

Spacing: `--spacing-xs` (0.25rem), `--spacing-sm` (0.5rem), `--spacing-button` (0.5rem), `--spacing-md` (1rem), `--spacing-lg` (1.5rem), `--spacing-xl` (2rem)

Sizes: `--width-form` (480px), `--width-button` (7rem)
