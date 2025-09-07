# Footer Menu Setup Guide

This theme includes a Bootstrap-based footer menu system that uses responsive column layout and includes social media integration.

## Structure

The footer uses a responsive Bootstrap grid layout with:
- **3 Dynamic Menu Sections**: Menu sections with titles and sub-items
- **Social Media Section**: Built-in social media icons with SVG images
- **Responsive Design**: Bootstrap grid that adapts to different screen sizes
- **Vision 2030 Image**: Additional branding element in the social media section

## Features

- **Bootstrap Grid System**: Responsive column layout using Bootstrap classes
- **Social Media Icons**: SVG-based social media icons with proper styling
- **Responsive Breakpoints**: Adapts from mobile to desktop layouts
- **Fallback Content**: Shows default content if no menu is configured

## Setup Instructions

### 1. Create Footer Menu in Drupal Admin

1. Go to **Structure > Menus** in your Drupal admin
2. Create a menu called `Footer Menu`
3. Add menu items as parent items (these will become section titles)
4. Add child items under each parent (these will be the links in each section)

### 2. Menu Structure Example

Create your footer menu with this structure:

```
Footer Menu
├── About the Center (parent)
│   ├── About the Center (child)
│   ├── Registration (child)
│   ├── Services (child)
│   ├── Coordinating Council (child)
│   ├── Media Center (child)
│   └── Center Initiatives (child)
├── Help & Support (parent)
│   ├── Contact Us (child)
│   ├── Submit a Complaint (child)
│   ├── Report Corruption (child)
│   ├── FAQ (child)
│   └── Help Center (child)
└── Important Links (parent)
    ├── Privacy Policy (child)
    ├── Freedom of Information Policy (child)
    ├── Terms of Use (child)
    └── Abuse Policy (child)
```

### 3. Create Menu Block

1. Go to **Structure > Block layout**
2. Click "Place block"
3. Choose "System menu block"
4. Select "Footer Menu"
5. Place in the footer region

### 4. Template Files

The theme includes:
- `templates/navigation/menu--footer.html.twig` - Main footer menu template
- `templates/layout/footer.html.twig` - Footer layout template

## Bootstrap Classes Used

The footer uses these Bootstrap CSS classes:

### Layout
- `row py-5` - Main container with padding
- `col-12 col-sm-6 col-lg-4 col-xl-3` - Responsive column classes
- `column mb-4` - Column styling with margin bottom

### Typography
- `column-title` - Section title styling
- `column-list` - List container styling

### Social Media
- `d-flex gap-2` - Flexbox layout for social icons
- `socialmedia-icon` - Social media icon styling
- `img-fluid mt-4` - Responsive image with top margin

## Social Media Section

The footer includes a built-in social media section with:
- Facebook, TikTok, Instagram, YouTube, LinkedIn icons
- SVG-based icons from `/frontend/img/` directory
- Vision 2030 branding image
- Responsive layout with proper spacing

## Responsive Breakpoints

The footer adapts to different screen sizes:
- **Mobile (xs)**: 1 column per row (`col-12`)
- **Small (sm)**: 2 columns per row (`col-sm-6`)
- **Large (lg)**: 3 columns per row (`col-lg-4`)
- **Extra Large (xl)**: 4 columns per row (`col-xl-3`)

## Fallback Content

If no menu block is placed, the footer displays default content with the same Bootstrap structure.

## Customization

### Adding More Menu Sections
The template currently supports up to 3 menu sections. To add more, modify the `|slice(0, 3)` filter in the template.

### Customizing Social Media Icons
Edit the social media section in `menu--footer.html.twig` to:
- Change icon paths (e.g., different SVG files)
- Add/remove social platforms
- Modify icon styling classes

### Styling Modifications
All styling uses Bootstrap classes, making it easy to:
- Change column layouts by modifying grid classes
- Adjust spacing with margin and padding classes
- Modify responsive behavior with breakpoint classes

## Template Variables

The footer template checks for:
- `page.footer_menu` - The main footer menu block

This is populated by the menu block placed in the footer region.

## File Structure

Required files for social media icons:
- `/frontend/img/fb.svg` - Facebook icon
- `/frontend/img/tiktok.svg` - TikTok icon
- `/frontend/img/instagram.svg` - Instagram icon
- `/frontend/img/youtube.svg` - YouTube icon
- `/frontend/img/linkedin.svg` - LinkedIn icon
- `/frontend/img/vision-2030.svg` - Vision 2030 branding 