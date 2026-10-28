# Wmarka Media — video, photo gallery and link list fields for Joomla articles

[Русский](README.md) · **English** · [Қазақша](README.kk.md)

Plugin `plg_fields_wmarkamedia` 1.1.3 · Joomla 5.2 and later, including 6.x · GNU GPL version 2 or later

## 1. What it is

One plugin provides three custom field types for articles:

| Field type | The editor enters | The reader sees |
|---|---|---|
| **Wmarka Media: video** | a video link, an embed code or a bare YouTube ID | a poster with a button; the player is created only on click. YouTube, Vimeo, VK, Rutube, Facebook, Instagram, TikTok, Telegram and .mp4/.webm files |
| **Wmarka Media: photo gallery** | rows of "photo, caption, credit" | a grid with captions and a viewer (arrows, Esc, "Photo 2 of 8") |
| **Wmarka Media: list of links** | one link per line, an optional title after "\|" | a list or buttons with the service name |

**Why one plugin and not one field.** In Joomla every file in a field plugin's `tmpl/` folder is a separate field type (the core "Media" plugin provides four of them). A video value is one string and a gallery value is a list of rows, so they cannot share a type, but they can share a plugin: one install, one parser of video links, one set of styles and one script.

## 2. Requirements and what was tested

- Joomla 5.2 or later; PHP 8.1 or later (Joomla 6.2 itself needs PHP 8.3).
- Tested on clean installs of **Joomla 6.2.0** and **Joomla 5.4.9**, PHP 8.3.6, MariaDB 10.11.14, Chromium without a window. The full protocol is in `docs/TESTS.md`.
- Not tested: Joomla 5.2–5.3 and 6.0–6.1, PHP 8.1/8.2/8.4, PostgreSQL, Apache and Nginx, real embeds of third-party services (TikTok, Instagram, Telegram, Facebook, VK, Rutube, Vimeo).

## 3. Install, update, remove

- **Install.** *Extensions → Install*, upload `plg_fields_wmarkamedia-<version>.zip` from the Releases page; or `php cli/joomla.php extension:install --path=plg_fields_wmarkamedia-<version>.zip`. The plugin enables itself.
- **Update.** Joomla announces new versions in the administrator (*System → Update → Extensions*): the plugin registers an update server (`updates.xml` in this repository). Installing a newer zip over the old one also works; settings and the enabled state are kept.
- **Remove.** *Extensions → Manage*, or `php cli/joomla.php extension:remove <id>`. Files, `media/` assets and language files are removed. Before removing, change the type of this plugin's fields or delete them, otherwise fields of an unknown type stay in the database.
- Interface languages: English, Russian, Kazakh.

## 4. Creating a field

*Content → Fields → New*, then choose a "Wmarka Media: …" type in the **Type** list.

- **Video.** Any title; automatic display "After Title" or "Before Display Content" works well; hide the label.
- **Photo gallery.** The article editor shows a table with the columns Photo, Caption and Credit and buttons to add, remove and move rows. The photo is the standard Media picker with an `alt` text.
- **List of links.** A textarea. Accepted lines: `https://t.me/example_channel`, `https://t.me/example_channel | Discuss in Telegram`, or a title glued directly to the link: `https://example.org/page Title`.

![The article form: video, vertical video and the gallery table](docs/screenshots/be-04-article-fields.png)

![A video field: plugin settings below the standard field options](docs/screenshots/be-02-field-video.png)

## 5. Video links understood

| Service | Accepted | Vertical | Poster |
|---|---|---|---|
| YouTube | `watch?v=`, `youtu.be/`, `/shorts/`, `/live/`, `/embed/`, a bare ID (also with `?si=…`), start time `t=1m30s` or `start=90` | `/shorts/` | yes (`i.ytimg.com`); `youtube-nocookie.com` by default |
| Vimeo | `vimeo.com/123456789`, channel and group links, private videos with `?h=…` | no | no |
| Rutube | `rutube.ru/video/…`, `/play/embed/…`, `/shorts/…` | `/shorts/` | no |
| VK | `vk.com/video-123_456`, `vkvideo.ru/…`, `/clip-…`, `video_ext.php` | `/clip` | no |
| Facebook | `/videos/…`, `/reel/…`, `/watch`, `fb.watch/…`, `/share/v/…`, `/share/r/…` | `/reel/`, `/share/r/` | no |
| Instagram | `/reel/…`, `/reels/…`, `/p/…`, `/tv/…` | `/reel/`, `/tv/` | no |
| TikTok | `tiktok.com/@name/video/123…`, `embed/v2/…`; **short links** (`vm.tiktok.com/…`) cannot be turned into a video number without a request to the service, so they stay a link | yes | no |
| Telegram | `t.me/channel/123` (public channels) | no (ratio 4:5) | no |
| File | `.mp4`, `.webm`, `.ogv`, `.mov`, `.m4v`, absolute or relative | no | no |
| Embed code | `<iframe …>` or a TikTok `<blockquote …>`: only the address is taken, the rest is discarded | by address | by address |
| Anything else | any `http(s)://…` link | no | no |

Every embed address is built from validated parts (IDs), never from the editor's text, so an arbitrary string cannot get into a `src` attribute.

## 6. Settings

General values are set in the plugin (*Extensions → Plugins → Fields - Wmarka Media*), own values on the **General** tab of each field. An empty value means "use settings from the plugin".

**Video:** player loading (on click · immediately), poster (provider thumbnail · no image), aspect ratio (auto · 16:9 · 9:16 · 4:5 · 1:1 · 4:3), maximum width of a vertical video (200–720 px, default 360), YouTube without cookies (yes), the "Open in …" link (yes), structured data (yes), CSS class.

**Photo gallery**

| Setting | Values | Default |
|---|---|---|
| Columns | 1–6 (at most two on a phone) | 3 |
| Viewer | yes · no | yes |
| Captions, credit | yes · no | yes |
| Image sizes | reduced copies (made automatically) · originals | copies |
| Grid image width, px | 200–2000 | 640 |
| Viewer image width, px | 800–4000 | 1920 |
| Show the first N photos | 0 — all; the rest open in the viewer and the last tile shows "+K" | 0 |
| Maximum number of photos | 0 — unlimited | 0 |
| Structured data, CSS class | yes · no, text | yes, empty |

**List of links:** block title, style (list · buttons), open in a new tab (yes), CSS class.

**Reduced copies.** Joomla itself makes the copies (GD library, the `Image` class), as WebP, or JPEG when GD cannot write WebP. They live in `media/plg_fields_wmarkamedia/cache`; the name carries the width and a fingerprint of the file, so replacing a photo gives a new copy, and the folder can be emptied at any time. One request makes at most 8 thumbnails and 2 large frames, the rest appear on later views; until a large copy exists the viewer takes the original, and heavy originals never reach the page itself. Originals lighter than 150 KB, or not wider than needed by more than 15 %, are not recompressed. Photos outside `images/`, external addresses and files above 36 megapixels are left as they are.

## 7. How it looks

Screenshots are from a test Joomla 6.2.0 with the standard Cassiopeia template; the gallery images are synthetic, and the YouTube poster is a test picture because external services were unreachable.

![Video: a YouTube facade and a vertical TikTok facade](docs/screenshots/fe-01-video.jpg)

![Gallery in the "first 6 photos" mode, the last tile shows +6](docs/screenshots/fe-02-gallery.jpg)

![The viewer](docs/screenshots/fe-03-lightbox.jpg)

## 8. Privacy and performance

- **Before a click** the page contacts a third-party server only for the YouTube poster (`i.ytimg.com`, one image). With "Poster: no image" there are no requests to third-party sites at all until the click.
- **After a click** the service's player loads and its cookies apply (for YouTube `youtube-nocookie.com` by default).
- Own assets: script 4.4 KB (1.7 KB gzipped) and styles 4.9 KB (1.8 KB). No libraries, no inline scripts except the structured-data block.
- The gallery's first frame loads at once with high priority (`loading="eager"`, `fetchpriority="high"`); the others are deferred.

Example measurement: Joomla 5.4.9, Cassiopeia, the same 2400×1600 photos (790 KB each), headless Chromium, network limited to "slow 4G" (1.6 Mbit/s, 150 ms), browser cache off, three runs, the median taken.

| | gallery of 8 photos | gallery of 38 photos, first 6 shown |
|---|---|---|
| requests | 24 | 22 |
| page weight, KB | 834 | 774 |
| of which photos, KB | 278 | 210 |
| of which plugin code, KB | 9 | 9 |
| of which template and Joomla core, KB | 530 | 530 |
| page HTML, KB | 16 | 24 |
| DOM nodes | 161 | 180 |
| LCP, s | 2.2 | 3.2 |

This is one machine, an emulated network and synthetic noisy photos (heavier to encode than typical ones), not field data.

## 9. Structured data

For video the plugin outputs `VideoObject` (name, description, date, embed URL, thumbnail) **only when a picture is known** (the YouTube poster or the article's cover image): without it search engines reject the markup, so none is written. For a gallery it outputs `ImageGallery` with a list of `ImageObject`. Both can be turned off in the settings.

## 10. Overriding layouts and using it in modules

- **Override.** `templates/<template>/html/plg_fields_wmarkamedia/wmarkavideo.php` (or `wmarkagallery.php`, `wmarkalinks.php`) replaces the plugin layout (tested). `$field`, `$fieldParams` and `$item` are available. Example: `examples/override-wmarkavideo.php`.
- **In module layouts.** The link parser is a class:
  ```php
  use Wmarka\Plugin\Fields\WmarkaMedia\Helper\VideoHelper;
  $ref = VideoHelper::parse($value);   // VideoRef or null
  // $ref->provider, ->label, ->id, ->page, ->embed, ->embedPlay, ->vertical, ->ratio, ->poster, ->kind
  ```
  To read the values for a whole list with one query instead of `FieldsHelper::getFields()` per article, see `examples/module-layout-one-query.php`.
- Styling: classes with the `wm-` prefix; colors and sizes are CSS variables (`--wm-accent`, `--wm-radius`, `--wm-ratio`, `--wm-max`, `--wm-cols`). See `examples/theme.css`.

## 11. Migrating from old fields

| Was | Becomes | Values |
|---|---|---|
| a YouTube or vertical-video field of another plugin | `wmarkavideo` | unchanged: bare IDs, IDs with `?si=…`, links and embed codes are all read |
| a textarea with links | `wmarkalinks` | unchanged: "link", "link \| title", "link title" are read |
| a subform gallery | `wmarkagallery` | rows are rewritten from `{"row0":{"field15":{…},"field14":"…"}}` to `{"row0":{"image":{…},"caption":"…","credit":""}}`; empty values (`[]`) can be removed |

`tools/migrate-fields.php` does it in one command. **By default it only shows what it would do**; writing needs `--apply`, a copy of the touched rows is saved first, and `--rollback` restores them:

```
php migrate-fields.php --video --links=social --galleries=13,19 --trash-subfields --purge-empty            # dry run
php migrate-fields.php --video --links=social --galleries=13,19 --trash-subfields --purge-empty --apply    # write
php migrate-fields.php --rollback=migrate-fields-backup-YYYYMMDD-HHMMSS.json                               # roll back
```

Put the script into the site root (next to `configuration.php`) or pass `--base=/path/to/site`. Field numbers are on *Content → Fields*. **Back up the database before `--apply`.** The script was tested on a test Joomla 6.2.0 with a reproduced field structure: dry run, write, the page, the administrator form, rollback (selects from the field and value tables matched the originals).

## 12. Development, tests and releases

| What | Where |
|---|---|
| plugin contents (what goes into the zip) | `src/` |
| build `dist/plg_fields_wmarkamedia-<version>.zip` and add an entry to `updates.xml` | `php build.php`; `--check` only checks; `--checksum` writes a sha256 (see `docs/PUBLISHING.md`) |
| checks without Joomla (67: link parsing, gallery, image paths, languages, manifest) | `php tests/run.php` |
| migration of old fields; layout, query and style examples | `tools/`, `examples/` |
| syntax check, tests on PHP 8.1–8.4, build, release on a `v*` tag | `.github/workflows/ci.yml` (not run yet: check the first run) |
| creating the repository and releasing a version | `docs/PUBLISHING.md` |

Pull requests and issues are welcome: run `php tests/run.php` and `php build.php --check` first; interface strings are in `src/language/<language>/`.

## 13. Limitations

- Short TikTok links stay links (section 5).
- Embeds of Facebook, Instagram, TikTok, Telegram, VK and Rutube depend on those services and on the privacy settings of each video; in tests their addresses were verified, the videos themselves were not.
- A poster is available only for YouTube (no request to service APIs is made).
- Reduced copies need the GD library and handle only files inside `images/` (JPEG, PNG, WebP). The first views create copies in portions, so open the page once after publishing. Old copies are not deleted when a photo is replaced.
- There is no "slider with a thumbnail strip" mode: that is a different layout, made with a layout override.
- Autoplay after the click depends on browser rules.

## 14. License and authorship

GNU GPL version 2 or later (`LICENSE`). The manifest names Beibit Sakhanov (https://webmarka.kz) as the author; to publish under another name change `author`, `authorUrl` and `copyright` in `src/wmarkamedia.xml` and in the file headers. History of changes — `CHANGELOG.md`.
