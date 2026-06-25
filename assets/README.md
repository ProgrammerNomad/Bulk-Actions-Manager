# WordPress.org plugin directory assets

This folder mirrors the **top-level `assets/` directory** on the WordPress.org plugin SVN repository (sibling of `trunk/`, not inside `trunk/` or the plugin zip).

## What belongs here

| File | Size | Required |
|------|------|----------|
| `screenshot-1.png` … `screenshot-9.png` | PNG or JPG, max 10MB each | Yes (one per `readme.txt` caption) |
| `banner-772x250.png` | Exactly 772×250 px | Recommended |
| `banner-1544x500.png` | Exactly 1544×500 px | Optional retina (requires base banner) |
| `icon-128x128.png` | Exactly 128×128 px | Recommended |
| `icon-256x256.png` | Exactly 256×256 px | Recommended retina |
| `icon.svg` | Vector | Optional; must also ship PNG fallback |

**Do not** place these files in `trunk/bulk-actions-manager/` or `bulk-actions-manager/assets/` (that folder is for plugin CSS/JS only).

## SVN upload notes

- Use **lowercase** filenames only.
- Set MIME types so images display instead of downloading:

```bash
svn propset svn:mime-type image/png *.png
svn propset svn:mime-type image/jpeg *.jpg
```

- CDN cache may take a few minutes (up to several hours under load) after upload.

## GitHub vs WordPress.org

- **WordPress.org:** commit this folder to SVN `assets/`; captions live in `trunk/bulk-actions-manager/readme.txt` only.
- **GitHub:** [`README.md`](../README.md) embeds `assets/screenshot-N.png` from this folder.

When replacing screenshots, overwrite files in place — do not renumber.

See [docs/SCREENSHOTS.md](../docs/SCREENSHOTS.md) for the capture checklist.
