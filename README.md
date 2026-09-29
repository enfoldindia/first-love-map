# First Love Map of India

**Recommended deployment: the WordPress plugin** in `wordpress-plugin/first-love-map/` (install steps in `wordpress-plugin/INSTALL.md`). The static site plus Google Apps Script backend described below is the fallback for when WordPress is not available.

A small public map where people pin a place in India and share a short "first love" story. Stories are reviewed before they appear.

## How it works

- **Frontend**: static HTML/CSS/JS (`index.html`, `app.js`, `style.css`) served by GitHub Pages. Leaflet and OpenStreetMap tiles load from public CDNs. There is no build step and no API key.
- **Backend**: a Google Apps Script web app (`apps-script/Code.gs`) bound to a private Google Sheet.
  - `GET` returns only rows whose `approved` box is ticked, cached for 60 seconds.
  - `POST` validates the story (1-5000 characters, year up to 20), checks the location is inside India, rounds it to about 1 km, rejects honeypot and too-fast submissions, and appends a row that is unapproved by default.
- **Moderation**: the Sheet is the moderation tool. Tick `approved` to publish, delete the row to remove. The public page has no moderator mode and no delete control.

## Files

| Path | Purpose |
| --- | --- |
| `index.html`, `app.js`, `style.css` | Public page |
| `config.js` | `APPS_SCRIPT_URL`; while empty the page shows a "not connected yet" notice |
| `apps-script/Code.gs` | Backend, pasted into the Sheet's Apps Script editor |
| `tests/code.test.js` | Tests for the validation and rounding functions |
| `wordpress-plugin/first-love-map/` | WordPress plugin (recommended): private post type, REST API, shortcode, embed view, settings, CSV importer |
| `wordpress-plugin/INSTALL.md` | Install and moderation guide for the WordPress plugin |
| `SETUP.md` | Step-by-step setup and moderation guide for staff |

## Test

```
node tests/code.test.js
```

## Privacy

Story data lives only in the private Sheet. Never commit exports of it to this public repository.
