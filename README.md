# Blt Gallery

A modern, self-contained WordPress photo gallery plugin with Cloudflare R2 / AWS S3 offloading, Cloudflare Images URL-based optimisation, and easy `[blt_gallery]` / `[blt_album]` shortcodes.

> Formerly distributed as **ZymGallery**. Existing `[zymgallery]` shortcodes and database tables are auto-migrated on activation — no content changes required.

## Features

- **Six display types**: Masonry, Tile Grid, Slideshow, Lightbox, Album, Image Slider
- **Three shortcodes**: `[blt_gallery]` (single gallery), `[blt_album]` (collection of galleries), and `[blt_slider]` (image slider from any mix of galleries + media library images)
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
| `cols`      | 1–8                                               | Target column count (responsive — reflows on narrow screens) |
| `size`      | `small` `medium` `large` `xlarge`                 | Minimum tile-width preset (advanced)  |
| `thumb_min` | px                                                | Raw minimum tile width (advanced)     |
| `gap`       | px                                                | Gutter between items                  |
| `radius`    | px                                                | Per-item border radius                |
| `pagination`| `off` `load-more` `numbered` `infinite`           | AJAX pagination mode                  |
| `per_page`  | int                                               | Images per page when paginated        |
| `date`      | `YYYY-MM-DD`                                      | Override the gallery's display date   |
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
| `sort_by`      | `menu` `date` `name` `random`           | Sort key within the album                |
| `order`        | `asc` `desc`                            | Sort direction                           |

### `[blt_slider]` — image slider

Build a lightweight slider from any mix of sources — whole galleries, specific gallery images, and images added **directly from the WordPress media library** — and drop it anywhere via shortcode. Every image is still delivered through the plugin's Cloudflare optimisation pipeline. A subtle caption (image description / photo credit), hover-reveal arrows, and a dot counter are built in.

```
[blt_slider galleries="5"]
[blt_slider galleries="5,7" autoplay="1" speed="6000"]
[blt_slider attachments="123,456,789"]
[blt_slider galleries="5" attachments="123" images="44,45"]
[blt_slider galleries="5" arrows="0" captions="off" loop="0"]
[blt_slider attachments="12,13" height="70vh" radius="12" class="my-hero"]
```

| Attribute     | Values                          | Notes                                              |
|---------------|---------------------------------|----------------------------------------------------|
| `galleries`   | comma-separated ints            | Gallery IDs whose images feed the slider           |
| `slugs`       | comma-separated slugs           | Galleries by slug (alternative to `galleries`)     |
| `images`      | comma-separated ints            | Specific Blt gallery image IDs                      |
| `attachments` | comma-separated ints            | WordPress media attachment IDs (add images directly) |
| `title`       | string                          | Accessible label for the carousel                  |
| `captions`    | `on` `off`                      | Show the subtle caption / photo credit             |
| `arrows`      | `1` / `0`                       | Show the hover-reveal nav arrows                   |
| `dots`        | `1` / `0`                       | Show the dot counter                               |
| `autoplay`    | `1` / `0`                       | Auto-advance slides                                |
| `speed`       | ms                              | Autoplay interval (default 5000)                   |
| `loop`        | `1` / `0`                       | Wrap from the last slide back to the first         |
| `height`      | `px` `vh` `%`                   | Max height of each slide, e.g. `70vh`              |
| `radius`      | px                              | Slider border radius                               |
| `order`       | `menu` `random` `reverse`       | Slide order                                        |
| `limit`       | int                             | Cap the number of slides rendered                  |
| `class`       | string                          | Extra CSS class on the wrapper                     |
| `style`       | string                          | Extra inline style on the wrapper                  |

## Cloudflare optimisation

Blt Gallery is built to run hot on Cloudflare:

1. **Cloudflare R2** — *Settings → Cloudflare R2*. Auto-offload new uploads, optionally remove the local copy.
2. **Cloudflare Image Resizing** — *Settings → Cloudflare Images*. Once enabled, the plugin rewrites every `<img>` `src` and `srcset` through `/cdn-cgi/image/` so each image is delivered in the optimal format (AVIF/WebP), size, and quality — without pre-generating extra thumbnails.
3. **Cache-Control** — R2 uploads are PUT with `Cache-Control: max-age=31536000` so the edge can hold them indefinitely.

See the companion [CloudflareSkills](https://github.com/sfxdotcom/CloudflareSkills) repository — in particular the `wordpress-on-cloudflare` skill — for end-to-end deployment patterns (cache rules, Workers, R2 binding, Images, page rules).

## License

GPL-3.0-or-later – see [LICENSE](license.txt).
