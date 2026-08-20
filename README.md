# BLT Gallery

A modern, self-contained WordPress photo gallery plugin with Cloudflare R2 / AWS S3 offloading, Cloudflare Images URL-based optimisation, and easy `[blt_gallery]` / `[blt_album]` shortcodes.

> Formerly distributed as **ZymGallery**. Existing `[zymgallery]` shortcodes and database tables are auto-migrated on activation — no content changes required.

## Features

- **Six display types**: Masonry, Tile Grid, Slideshow, Lightbox, Album, Image Slider
- **Three shortcodes**: `[blt_gallery]` (single gallery), `[blt_album]` (collection of galleries), and `[blt_slider]` (image slider)
- **Visual slider builder**: assemble a slider from your galleries + the media library under **BLT Gallery → Sliders**, then copy its shortcode
- **Rich shortcode attributes** for inline styling — `cols`, `gap`, `radius`, `captions`, `autoplay`, etc.
- **No external dependencies**: standalone plugin — no NextGEN Gallery required
- **Background migrations**: import from NextGEN Gallery or Modula with a live progress bar — the copy runs on the server, so you can close the tab
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

### Updates

Updates come from this repository's GitHub releases, and are **checked manually only**. Nothing phones home on a schedule or on admin page loads — the plugin contacts GitHub when you ask it to:

- **Plugins → BLT Gallery → Check for updates**, or
- the **Check again** button on **Dashboard → Updates**.

Whatever the last check found stays cached and keeps being offered until you check again. To restore periodic checks, filter the interval back to a positive number of hours:

```php
add_filter( 'bltgallery_update_check_period', fn() => 12 );
```

For a private repo or higher API rate limits, set a GitHub token in `wp-config.php`:

```php
define( 'BLT_GALLERY_GITHUB_TOKEN', 'ghp_…' );
```

## Brand assets

The BLT mark lives in `assets/img/`:

| File | Use |
|------|-----|
| `blt-gallery-mark.svg` | Master artwork — `fill="currentColor"`, so it takes the colour of wherever it's dropped |
| `icon-128x128.png`, `icon-256x256.png` | Plugin card in **Dashboard → Updates** |
| `favicon-16x16.png`, `favicon-32x32.png`, `apple-touch-icon.png` | Favicons |
| `site-icon-512x512.png` | Upload under **Settings → General → Site Icon** to use it as the site favicon |

The admin menu icon is generated from the master SVG at runtime, so replacing that one file re-skins everything.

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

Build a slider visually under **BLT Gallery → Sliders**: create one, add images from the **media library** and/or your **galleries**, drag to reorder, add an optional caption (description / photo credit) per slide, tweak the options, and copy the generated shortcode. A subtle caption, hover-reveal arrows, and a dot counter are built in, and every image is delivered through the plugin's Cloudflare optimisation pipeline.

```
[blt_slider id="3"]
[blt_slider slug="homepage-hero"]
[blt_slider id="3" autoplay="1" speed="6000" height="60vh"]
```

Per-placement attributes (any saved option can be overridden inline):

| Attribute     | Values                          | Notes                                              |
|---------------|---------------------------------|----------------------------------------------------|
| `id`          | int                             | Saved slider ID (primary)                          |
| `slug`        | string                          | Saved slider slug (alternative to `id`)            |
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

For code-only sliders you can skip the builder and source images inline instead of `id`/`slug`:

```
[blt_slider galleries="5,7"]
[blt_slider attachments="123,456" arrows="0" captions="off"]
[blt_slider galleries="5" attachments="123" images="44,45"]
```

| Attribute     | Values                          | Notes                                              |
|---------------|---------------------------------|----------------------------------------------------|
| `galleries`   | comma-separated ints            | Gallery IDs whose images feed the slider           |
| `slugs`       | comma-separated slugs           | Galleries by slug                                  |
| `images`      | comma-separated ints            | Specific BLT gallery image IDs                      |
| `attachments` | comma-separated ints            | WordPress media attachment IDs                      |

## Storage and offloading

With **Settings → Integrations → Cloudflare R2** (or Amazon S3) switched on, every image is pushed to the bucket as it is created — uploads through the admin and images pulled in by a migration alike. Each gallery gets its own folder, mirroring the local layout:

```
[path prefix/]galleries/12-annual-symposium/photo.jpg
[path prefix/]galleries/12-annual-symposium/thumbs/thumb/photo-thumb.webp
[path prefix/]galleries/12-annual-symposium/thumbs/medium/photo-medium.webp
[path prefix/]galleries/12-annual-symposium/thumbs/large/photo-large.webp
```

Offloading is best effort: an unreachable or misconfigured bucket leaves the image local and working rather than failing the upload. It only runs going forward, at the moment an image is created — turning R2 or S3 on doesn't retroactively touch anything already sitting on local disk.

### Pushing existing images to remote storage

Once a backend is enabled, **Settings → General → Existing images** shows how many images are still local and a button to push them across. This is the same kind of background job as a migration: time-boxed passes dispatched by WP-Cron plus an immediate loopback ping, so the run continues after you close the page, and resumes on its own if the site's scheduler stalls. Reopening Settings shows the live progress bar for whichever run is already in flight.

The backend is pinned the moment the run starts, so flipping the Settings toggle mid-run can't send half the images to one bucket and the rest to another. An image that fails to upload — bad credentials, a file the bucket rejects, a quota — is skipped with a warning rather than retried forever; press the button again later to give it another pass. Cancelling keeps whatever was already pushed.

### Deleting

Deleting a gallery removes its images and the files behind them. Local files go immediately; objects in R2 or S3 are handed to a background queue and cleared on WP-Cron, because a thousand-image gallery is several thousand bucket requests and no admin screen should wait on that. The queue holds off briefly first so bulk-deleting a dozen galleries drains in one pass rather than a dozen.

Two filters tune it: `bltgallery_storage_purge_delay` (seconds before a drain starts, default 60) and `bltgallery_storage_purge_budget` (seconds of deleting per pass).

## Migrating from another gallery plugin

**BLT Gallery → Migrate** imports galleries from **Imagely NextGEN Gallery** and **Modula**. Pick the galleries you want and press *Import Selected Galleries*; your originals are only ever read from — every image is copied into BLT Gallery's own upload directory.

Migrations run as a background job, so a library of several thousand photos is no longer bound by how long a single HTTP request may run:

- **Progress is live.** The page shows a progress bar, the gallery being copied, images done vs. total, elapsed time, and an estimate of what's left — plus a per-gallery breakdown and any warnings.
- **You can close the page.** The copy continues on the server. Reopen *Migrate* at any time and the panel picks the run back up where it is.
- **It resumes, it doesn't restart.** Work is saved after every few images, so a PHP timeout, a memory ceiling, or a restarted worker costs seconds rather than the whole run.
- **You can stop it.** *Cancel migration* halts the run; galleries copied up to that point are kept.
- **Migrated galleries are ticked off.** Each imported gallery records where it came from, and the picker shows that source gallery as *Imported* — unticked, with a link to its copy — so a second run doesn't quietly duplicate it. A run that was interrupted shows as *Partly imported* instead. Galleries brought across before the plugin tracked this are recognised by their slug and labelled as a name match.

Passes are triggered by WP-Cron plus an immediate loopback request. If a host blocks both, the page notices the job has stalled and drives it in the foreground instead — leave the tab open in that case.

Once a NextGEN migration finishes, a **Clean up NextGEN Gallery files** panel appears so you can ZIP and then remove the legacy files on disk.

### Extending

Any class implementing `BltGallery\Import\SourceImporter` can be driven by the same background worker — register it with the `bltgallery_import_source` filter. Two other filters tune the worker: `bltgallery_import_time_budget` (seconds of work per pass) and `bltgallery_import_slice_size` (images between progress saves).

## Cloudflare optimisation

BLT Gallery is built to run hot on Cloudflare:

1. **Cloudflare R2** — *Settings → Cloudflare R2*. Auto-offload new uploads, optionally remove the local copy.
2. **Cloudflare Image Resizing** — *Settings → Cloudflare Images*. Once enabled, the plugin rewrites every `<img>` `src` and `srcset` through `/cdn-cgi/image/` so each image is delivered in the optimal format (AVIF/WebP), size, and quality — without pre-generating extra thumbnails.
3. **Cache-Control** — R2 uploads are PUT with `Cache-Control: max-age=31536000` so the edge can hold them indefinitely.

See the companion [CloudflareSkills](https://github.com/sfxdotcom/CloudflareSkills) repository — in particular the `wordpress-on-cloudflare` skill — for end-to-end deployment patterns (cache rules, Workers, R2 binding, Images, page rules).

## License

GPL-3.0-or-later – see [LICENSE](license.txt).
