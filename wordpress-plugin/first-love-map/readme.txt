=== First Love Map ===
Contributors: enfold
Tags: map, stories, moderation, leaflet
Requires at least: 5.8
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A moderated map of India where visitors pin a place and share a short first-love story.

== Description ==

Adds the `[first_love_map]` shortcode: a map of India, a submission form and a "Read stories" list.

* Submissions are stored as a private post type ("Love Map Memories") with no public URL. They are excluded from site search and sitemaps.
* New memories wait as Pending until an editor publishes them (switchable in Settings -> First Love Map).
* Only editors and administrators can see or manage memories.
* Spam protection: honeypot field, minimum time on the form, and a per-visitor rate limit of 5 submissions per 10 minutes. The limit uses a salted hash of the visitor address; raw addresses are never stored.
* Locations are rounded (default 2 decimals, about 1 km) before saving.
* All visitor text is displayed as plain text.
* `?flm_embed=1` on the site address serves a bare full-page map for use in an iframe on allowed sites.
* Leaflet 1.9.4 is bundled; no scripts are loaded from a CDN. Map tiles are loaded by the visitor's browser from OpenStreetMap.

REST API: `GET /wp-json/flm/v1/memories` (published memories only, cached for 60 seconds) and `POST /wp-json/flm/v1/memories` (public submission).

== Installation ==

1. Upload the zip via Plugins -> Add New -> Upload Plugin and activate it.
2. Open Settings -> First Love Map to set the notification email and import existing stories.
3. Add the `[first_love_map]` shortcode to a page.

== Frequently Asked Questions ==

= Are memories deleted when the plugin is deleted? =

No. To remove settings and all memories on uninstall, define `FLM_REMOVE_ALL_DATA` as `true` in wp-config.php before deleting the plugin.

= Bundled libraries =

Leaflet 1.9.4, BSD 2-Clause licence (see assets/leaflet/LICENSE.txt).

== Changelog ==

= 1.0.2 =
* The embed view now always fills the height of its iframe. On narrow widths it stacks the title bar, map and side panel, and the panel scrolls on its own.
* Form inputs use a 16px font on narrow widths so iOS does not zoom on focus.

= 1.0.1 =
* Fix map tiles covering only part of the map area when the embed is resized or laid out after load.

= 1.0.0 =
* First release.
