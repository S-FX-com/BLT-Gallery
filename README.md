# Blt Gallery

A modern, self-contained WordPress photo gallery plugin with Cloudflare R2 / AWS S3 offloading, Cloudflare Images URL-based optimisation, and easy `[blt_gallery]` / `[blt_album]` shortcodes.

> Formerly distributed as **ZymGallery**. Existing `[zymgallery]` shortcodes and database tables are auto-migrated on activation — no content changes required.

## Features

- **Five display types**: Masonry, Tile Grid, Slideshow, Lightbox, Album
- **Two shortcodes**: `[blt_gallery]` (single gallery) and `[blt_album]` (collection of galleries)
- **Rich shortcode attributes** for inline styling — `cols`, `gap`, `radius`, `captions`, `autoplay`, etc.
- **No external dependencies**: standalone plugin — no NextGEN Gallery required
- **REST API**: full CRUD via the WordPress REST API (`/bltgallery/v1/`)
- **Image optimisation**: WebP/AVIF thumbnails generated on upload; EXIF stripped
- **Cloudflare R2 offloading** (S3 SigV4, no SDK dependency)
- **AWS S3 + CloudFront offloading** (signed URLs optional)
- **Cloudflare Image Resizing** integration — point the plugin at your zone and every image is delivered via `/cdn-cgi/image/` at the exact pixel size and format requested
- **Accessibility**: WCAG 2.2 AA — keyboard navigation, ARIA roles, focus traps
- **Modern CSS**: CSS Grid, custom properties, no jQuery
- **WordPress 7 ready** (still supports 6.3+): PHP 8.1+, deferred-loading scripts, fetchpriority="high" on LCP image

## Requirements

- WordPress 6.3+ (tested up to 7.0)
- PHP 8.1+
- Composer (PHP dependencies; ships with a fallback PSR-4 autoloader)

## Installation

```bash
composer install --no-dev --optimize-autoloader
```

Upload to `/wp-content/plugins/blt-gallery/` and activate via **Plugins**.

## Shortcode reference

### `[blt_gallery]` — single gallery

```
[blt_gallery id="5"]
[blt_gallery id="5" type="slideshow" autoplay="1" speed="4000"]
[blt_gallery slug="my-gallery" type="masonry" cols="4" gap="16" radius="12"]
[blt_gallery id="5" type="tile" cols="5" gap="8" captions="hover" lightbox="1"]
[blt_gallery id="5" limit="12" order="random"]
[blt_gallery id="5" class="my-section" style="background:#000;padding:24px"]
```

| Attribute   | Values                                            | Notes                                 |
|-------------|---------------------------------------------------|---------------------------------------|
| `id`        | int                                               | Gallery ID (or use `slug`)            |
| `slug`      | string                                            | Gallery slug (alt to `id`)            |
| `type`      | `masonry` `tile` `slideshow` `lightbox` `album`   | Overrides stored display type         |
| `size`      | `small` `medium` `large` `xlarge`                 | Responsive thumbnail size (preferred) |
| `thumb_min` | px                                                | Raw min-column-width override         |
| `cols`      | 1–8                                               | Force a fixed column count (overrides `size`) |
| `gap`       | px                                                | Gutter between items                  |
| `radius`    | px                                                | Per-item border radius                |
| `captions`  | `below` `hover` `off`                             | Caption position                      |
| `lightbox`  | `1` / `0`                                         | Enable lightbox click-through         |
| `autoplay`  | `1` / `0`                                         | Slideshow only                        |
| `speed`     | ms                                                | Slideshow autoplay interval           |
| `arrows`    | `1` / `0`                                         | Slideshow nav arrows                  |
| `dots`      | `1` / `0`                                         | Slideshow dot indicators              |
| `limit`     | int                                               | Cap number of images rendered         |
| `order`     | `menu` `date` `random`                            | Sort order                            |
| `class`     | string                                            | Extra wrapper class                   |
| `style`     | string                                            | Extra wrapper inline style            |

### `[blt_album]` — collection of galleries

```
[blt_album ids="3,7,9"]
[blt_album ids="3,7,9" style="grid" cols="3" gap="20" captions="below"]
[blt_album slugs="weddings,nature,travel" style="masonry" cols="4"]
[blt_album ids="3,7,9" style="carousel" cols="4"]
[blt_album ids="3,7,9" style="accordion" gallery_type="masonry"]
[blt_album category="portfolio" limit="12" order="date"]
```

| Attribute      | Values                                  | Notes                                    |
|----------------|-----------------------------------------|------------------------------------------|
| `ids`          | comma-separated ints                    | Galleries to include                     |
| `slugs`        | comma-separated slugs                   | Alternative to `ids`                     |
| `category`     | string                                  | Match `settings.category` on galleries   |
| `style`        | `grid` `masonry` `carousel` `accordion` | Album layout                             |
| `cols`         | 1–8                                     | Cards per row                            |
| `gap`          | px                                      | Gap between cards                        |
| `radius`       | px                                      | Card border radius                       |
| `captions`     | `below` `hover` `off`                   | Title placement on card                  |
| `show_count`   | `1` / `0`                               | Render "N photos" under each card        |
| `cover`        | `first` `random`                        | Which image to use for the card cover    |
| `gallery_type` | same as `[blt_gallery]` `type`          | Inline display type in accordion mode    |
| `limit`        | int                                     | Cap number of galleries rendered         |
| `order`        | `menu` `date` `random`                  | Sort order                               |

## Cloudflare optimisation

Blt Gallery is built to run hot on Cloudflare:

1. **Cloudflare R2** — *Settings → Cloudflare R2*. Auto-offload new uploads, optionally remove the local copy.
2. **Cloudflare Image Resizing** — *Settings → Cloudflare Images*. Once enabled, the plugin rewrites every `<img>` `src` and `srcset` through `/cdn-cgi/image/` so each image is delivered in the optimal format (AVIF/WebP), size, and quality — without pre-generating extra thumbnails.
3. **Cache-Control** — R2 uploads are PUT with `Cache-Control: max-age=31536000` so the edge can hold them indefinitely.

See the companion [CloudflareSkills](https://github.com/sfxdotcom/CloudflareSkills) repository — in particular the `wordpress-on-cloudflare` skill — for end-to-end deployment patterns (cache rules, Workers, R2 binding, Images, page rules).

## License

GPL-3.0-or-later – see [LICENSE](license.txt).
