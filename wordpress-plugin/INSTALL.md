# Installing First Love Map on WordPress

For the site administrator. No coding needed.

## 1. Install

1. In wp-admin go to **Plugins -> Add New -> Upload Plugin**.
2. Choose `first-love-map-plugin.zip` and click **Install Now**, then **Activate**.

## 2. Settings

Go to **Settings -> First Love Map**.

- **Review before publishing**: leave ticked. New stories wait as Pending.
- **Notification email**: the address that gets a "new memory awaiting review" email. The email has a link only, never the story text.
- **Allowed embed origins**: sites allowed to show the map in an iframe (Framer is pre-filled).
- **Import existing stories**: upload the CSV (columns `id, createdAt, lat, lon, year, story, approved`). Approved rows are published with their original date. Importing the same file again adds nothing twice. Delete the CSV from your computer's shared folders afterwards; it contains story text.

## 3. Add the map to a page

Create a page and add the shortcode `[first_love_map]`. In Elementor, drag in a **Shortcode** widget and paste it there. Give the map a wide column (at least 900 px) so the story panel sits beside the map.

## 4. Moderate

Go to **Love Map Memories -> Pending**.

- **Publish**: tick the memories, choose Bulk actions -> Edit -> Status: Published (or open one and click Publish). It appears on the map within about a minute.
- **Remove**: move it to Trash. It disappears from the map.
- To remove identifying details, open the memory, edit the text, then publish.

Only Editors and Administrators can see this menu.

## 5. Show the map on Framer (or another site)

Add the site's address to **Allowed embed origins**, then embed:

```html
<iframe src="https://YOUR-SITE/?flm_embed=1" title="First Love Map of India"
        style="width:100%;height:100vh;border:0" loading="lazy"></iframe>
```

## Notes

- **Cloudflare** may cache the story list (`/wp-json/flm/v1/memories`) for up to a minute, so a newly published story can take about a minute to show.
- If a security plugin blocks the REST API for visitors, allow the `flm/v1` namespace, or the map cannot load or accept stories.
- Map tiles come from OpenStreetMap, loaded by the visitor's browser.
- Deleting the plugin keeps all stories. To wipe everything on deletion, add `define( 'FLM_REMOVE_ALL_DATA', true );` to wp-config.php first.
- Requires WordPress 5.8+ and PHP 7.4+ (the live site reports WordPress 6.9.9).
