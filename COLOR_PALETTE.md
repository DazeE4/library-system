# Corporate Design Color Reference

## Color Palette

### Primary Colors
- **Corporate Blue** - #1E40AF
  - Used for: Buttons, headers, primary actions, links
  - RGB: rgb(30, 64, 175)
  - Hex: #1E40AF

- **Deep Blue** - #1e3a8a
  - Used for: Hover states, dark accents, gradients
  - RGB: rgb(30, 58, 138)
  - Hex: #1e3a8a

- **Bright Blue** - #3b82f6
  - Used for: Light accents, secondary text links
  - RGB: rgb(59, 130, 246)
  - Hex: #3b82f6

### Secondary Colors
- **Corporate Teal** - #0F766E
  - Used for: Success states, secondary actions, returns
  - RGB: rgb(15, 118, 110)
  - Hex: #0F766E

- **Professional Cyan** - #0369A1
  - Used for: Accent colors, special actions
  - RGB: rgb(3, 105, 161)
  - Hex: #0369A1

### Status Colors
- **Success Green** - #059669
  - Used for: Confirmations, positive actions
  - Hex: #059669

- **Warning Orange** - #D97706
  - Used for: Warnings, alerts
  - Hex: #D97706

- **Error Red** - #DC2626
  - Used for: Errors, dangerous actions
  - Hex: #DC2626

### Background Colors
- **Pure White** - #FFFFFF
  - Used for: Main background, card backgrounds
  - Hex: #FFFFFF

- **Light Gray** - #F3F4F6
  - Used for: Secondary backgrounds, hover states
  - Hex: #F3F4F6

### Text Colors
- **Primary Text (Dark)** - #111827
  - Used for: Main text, headings
  - Hex: #111827

- **Secondary Text (Gray)** - #4B5563
  - Used for: Supporting text, descriptions
  - Hex: #4B5563

- **Light Text (Light Gray)** - #9CA3AF
  - Used for: Disabled text, subtle information
  - Hex: #9CA3AF

### Border Colors
- **Light Border** - #E5E7EB
  - Used for: Component borders, dividers
  - Hex: #E5E7EB

---

## Shadow System

### Professional Shadows
```css
--shadow-sm: 0 1px 2px 0 rgba(0,0,0,0.05);
--shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1);
--shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1);
--shadow-xl: 0 20px 25px -5px rgba(0,0,0,0.1);
```

---

## Component Colors

### Buttons
- **Primary Button**: #1E40AF (Corporate Blue)
- **Secondary Button**: #0F766E (Corporate Teal)
- **Hover**: Darker shade with shadow
- **Disabled**: #9CA3AF (Light Gray)

### Status Badges
- **Borrowed**: Blue (#1E40AF) - 10% opacity background
- **Returned**: Teal (#0F766E) - 10% opacity background
- **Overdue**: Red (#DC2626) - 10% opacity background
- **Pending**: Cyan (#0369A1) - 10% opacity background

### Toast Notifications
- **Success**: Teal (#0F766E)
- **Error**: Red (#DC2626)
- **Warning**: Orange (#F97316)
- **Info**: Blue (#1E40AF)

### Tables
- **Header**: Blue (#1E40AF) background, white text
- **Row Hover**: Light Gray (#F3F4F6)
- **Border**: Light Border (#E5E7EB)

---

## Typography

### Font Family
```css
font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Oxygen', 'Ubuntu', 'Cantarell', sans-serif;
```

### Heading Sizes
- **H1**: 2rem (32px) - Page titles
- **H2**: 1.75rem (28px) - Section titles
- **H3**: 1.5rem (24px) - Subsection titles
- **H4**: 1.25rem (20px) - Component titles
- **Paragraph**: 0.95rem (15px) - Body text

---

## Spacing System

- **xs**: 0.25rem (4px)
- **sm**: 0.5rem (8px)
- **md**: 1rem (16px)
- **lg**: 1.5rem (24px)
- **xl**: 2rem (32px)
- **2xl**: 3rem (48px)

---

## Border Radius

- **Sharp**: 0px - For precise elements
- **Small**: 6px - Buttons, inputs, small containers
- **Medium**: 8px - Cards, modals, medium containers
- **Large**: 12px - Hero sections, large containers
- **Full**: 9999px - Pills, badges

---

## Transitions

```css
transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
```

Used for:
- Color changes
- Shadow changes
- Border changes
- Background changes

---

## CSS Variables Usage

```css
:root {
    --primary: #1E40AF;
    --primary-dark: #1e3a8a;
    --primary-light: #3b82f6;
    --secondary: #0F766E;
    --accent: #0369A1;
    --success: #059669;
    --warning: #D97706;
    --danger: #DC2626;
    --bg-primary: #FFFFFF;
    --bg-secondary: #F3F4F6;
    --text-primary: #111827;
    --text-secondary: #4B5563;
    --text-light: #9CA3AF;
    --border: #D1D5DB;
    --border-light: #E5E7EB;
    --shadow-sm: 0 1px 2px 0 rgba(0,0,0,0.05);
    --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1);
    --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1);
    --shadow-xl: 0 20px 25px -5px rgba(0,0,0,0.1);
    --transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}
```

Usage in CSS:
```css
.button {
    background: var(--primary);
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 6px;
    border: none;
    font-weight: 600;
    cursor: pointer;
    transition: var(--transition);
}

.button:hover {
    background: var(--primary-dark);
    box-shadow: var(--shadow-md);
}
```

---

## Accessibility Compliance

- **Contrast Ratio**: All text meets WCAG AA standards (4.5:1 minimum)
- **Focus States**: Blue outline visible on all interactive elements
- **Color Blindness**: Design doesn't rely solely on color for information
- **Typography**: Readable font sizes and weights throughout

---

## Integration Guide

To use this color palette in your design:

1. **Colors**: Use CSS variables from `:root`
2. **Shadows**: Apply professional shadows for depth
3. **Spacing**: Use rem units for consistent sizing
4. **Typography**: Apply system font stack
5. **Transitions**: Use 0.2s ease for all interactive changes

Example:
```html
<button style="background: var(--primary); color: white; padding: 0.75rem 1.5rem; border-radius: 6px; border: none; font-weight: 600; cursor: pointer; transition: var(--transition);">
    Action
</button>
```

---

*This color palette provides a professional, enterprise-grade design system suitable for corporate applications.*
