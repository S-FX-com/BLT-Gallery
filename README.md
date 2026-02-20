# ZymGallery

A modern, self-contained WordPress photo gallery plugin with AWS S3/CloudFront offloading, WebP image optimisation, and beautiful responsive display types.

## Features

- **Four display types**: Masonry, Tile Grid, Slideshow, Lightbox
- **No external dependencies**: standalone plugin — no NextGEN Gallery required
- **Modern admin UI**: React-powered admin built on `@wordpress/components`
- **REST API**: full CRUD via the WordPress REST API (`/zymgallery/v1/`)
- **Image optimisation**: WebP thumbnails generated on upload via `WP_Image_Editor`; EXIF data stripped for privacy
- **AWS S3 offloading**: auto-upload to S3 on ingest; keeps local files or deletes after transfer
- **CloudFront CDN**: serves images from a CloudFront distribution (signed URLs optional)
- **Accessibility**: WCAG 2.2 AA — keyboard navigation, ARIA roles, focus traps in lightbox
- **Modern CSS**: CSS Grid, CSS custom properties, no jQuery

## Requirements

- WordPress 6.3+
- PHP 8.1+
- Composer (for PHP dependencies)
- Node.js 18+ / npm (for asset builds)

## Installation

### 1. Install PHP dependencies

```bash
composer install --no-dev --optimize-autoloader
```

### 2. Build frontend assets

```bash
npm install
npm run build
```

### 3. Activate in WordPress

Upload the plugin folder to `/wp-content/plugins/zymgallery/` and activate via **Plugins**.

## Usage

### Shortcode

```
[zymgallery id="5"]
[zymgallery id="5" type="slideshow"]
[zymgallery slug="my-gallery" type="masonry"]
```

### Display types

| Value       | Description                                        |
|-------------|-----------------------------------------------------|
| `masonry`   | CSS columns masonry grid with optional lightbox     |
| `tile`      | Uniform square thumbnail grid                       |
| `slideshow` | Accessible carousel with autoplay, dots, arrows     |
| `lightbox`  | Thumbnail grid that opens a full-screen modal       |

## Development

```bash
npm start          # webpack dev watch
npm run lint       # JS + CSS linting
composer test      # PHPUnit tests
```

## AWS S3 / CloudFront Setup

1. Create an S3 bucket and an IAM user with `s3:PutObject`, `s3:DeleteObject` permissions.
2. (Optional) Create a CloudFront distribution pointing at the bucket.
3. Enter credentials in **ZymGallery → Settings → AWS S3 & CloudFront**.
4. Enable **Auto-offload new uploads to S3**.

## License

GPL-3.0-or-later – see [LICENSE](license.txt).
