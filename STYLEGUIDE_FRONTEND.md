# Frontend Styleguide

## Principles

1. **IDs for unique pages** - Each page gets a unique ID on `<main>`
2. **Element selectors over classes** - Within an ID context, prefer element selectors
3. **Data attributes for JS hooks** - Use `data-*` attributes for JavaScript, not for styling
4. **Classes only when necessary** - Only for reusable components or states

## Page IDs

| Page | ID | Description |
|------|-----|-------------|
| Login | `#login` | Login form |
| Setup | `#setup` | Initial setup with TOTP |
| Feed | `#feed` | Main view with feed list |
| Subscriptions | `#subscriptions` | Manage subscriptions |
| Onboarding | `#onboarding` | Add first feed (standalone feature) |

## Structure

```
body
├── header
│   ├── h1 (Logo/Title)
│   └── nav (Navigation)
├── main#[page-id]
│   └── [Page-specific content]
└── footer
```

## Auth Pages Structure

```
main#login, main#setup, main#onboarding
├── p (Intro text)
├── p[role="alert"] (Error message, optional)
└── form
    ├── label + input (Fields)
    └── button (Submit)
```

## Feed Page Structure

```
main#feed
├── aside (Sidebar with subscriptions)
│   └── ul > li > a (Feed links)
├── section (Reading list)
│   └── article > a (Feed items)
└── article (Reading pane)
    ├── header (Title, meta)
    ├── div (Content)
    └── footer (Actions)
```

## Allowed Classes

### States
- `.active` - Active element (navigation, feed item)
- `.read` - Read item

### Reusable Components
- `.otp-inputs` - 6-digit OTP input
- `.btn-link` - Button styled as link

### Layout Helpers (use sparingly)
- `.inline` - `display: inline`

## Forbidden Patterns

- No `.form-group`, `.form-control`, `.container` wrappers
- No BEM notation (`.block__element--modifier`)
- No utility classes (`.mt-4`, `.flex`, `.text-center`)
- No nested classes (`.auth-page .auth-container .auth-form`)

## Examples

### Good
```css
#login form > label {
    display: block;
    margin-bottom: 0.25rem;
}

#login form > input {
    width: 100%;
    padding: 0.5rem;
}
```

### Bad
```css
.auth-page .auth-container .form-group label {
    display: block;
}

.auth-form-input {
    width: 100%;
}
```

## CSS Custom Properties

All variables defined in `:root`:

### Colors
- `--color-text` - Text color
- `--color-link` - Link color
- `--color-border` - Border color
- `--color-bg` - Background color
- `--color-bg-muted` - Muted background
- `--color-new` - New items indicator

### Spacing
- `--spacing-xs` - 0.25rem
- `--spacing-sm` - 0.5rem
- `--spacing-md` - 1rem
- `--spacing-lg` - 1.5rem
- `--spacing-xl` - 2rem

### Sizes
- `--size-otp-input` - OTP input field
- `--size-qr-code` - QR code size
