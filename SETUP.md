# Setup guide (for Shivangi)

You will create a private Google Sheet that holds the stories, and connect it to the map. It takes about 15 minutes and needs no coding. Use the Google account that should own the data.

## 1. Create the Sheet

1. Go to https://sheets.google.com and create a **Blank** spreadsheet. Name it `First Love Map stories`.
2. In row 1, type these headers exactly, one per cell, from A1 to G1 (lowercase, same spelling):

   | A | B | C | D | E | F | G |
   | --- | --- | --- | --- | --- | --- | --- |
   | id | createdAt | lat | lon | year | story | approved |

3. **Keep the Sheet private.** Do not use Share -> "Anyone with the link". Share it only with named colleagues who moderate. Stories that are not yet approved are visible in this Sheet, so it must never be public.

## 2. Add the backend code

1. In the Sheet, click **Extensions -> Apps Script**.
2. Delete everything in the editor.
3. Open `apps-script/Code.gs` (the maintainer will send it, or copy it from the GitHub repository) and paste all of it in.
4. Click the disk icon (Save).

## 3. Deploy it

1. Click **Deploy -> New deployment**.
2. Click the gear icon next to "Select type" and choose **Web app**.
3. Set **Execute as: Me** and **Who has access: Anyone**. (This only lets people send a story and read approved stories. The Sheet itself stays private.)
4. Click **Deploy**. Google will ask you to authorize: choose your account, click **Advanced -> Go to (project name) -> Allow**.
5. Copy the **Web app URL**. It ends in `/exec`.
6. **Send that URL to the maintainer.** They will connect the map to it. Nothing shows on the map until this is done.

If you later change the code, use **Deploy -> Manage deployments -> Edit (pencil) -> Version: New version -> Deploy**. The URL stays the same.

## 4. Moderate stories

Every new submission is added as a new row with the `approved` box **unticked**, so nobody sees it yet.

- **Publish a story**: read it in column F, then tick the box in column `approved`.
- **Hide a story**: untick the box.
- **Remove a story for good**: right-click the row number and choose **Delete row**.

Changes appear on the map within about a minute.

Before ticking, check that the story has no names, school names, phone numbers, social handles or other identifying details. If it does, delete the row (or edit the text in the cell first).

## 5. Import the existing stories

The maintainer will send you a file called `existing-stories.csv` (do not post it publicly; it contains people's stories).

1. In the Sheet, click **File -> Import -> Upload** and choose the file.
2. Set **Import location: Append to current sheet**. Untick "Convert text to numbers, dates and formulas" if offered.
3. Click **Import data**.
4. Select the `approved` cells of the imported rows (column G) and click **Insert -> Checkbox**. Cells that say TRUE become ticked boxes, and those stories are shown.

If the header row appears twice, delete the extra copy.

## 6. Put the map on the Framer site

In Framer, add an **Embed** element (Insert -> Embed), choose **HTML**, and paste:

```html
<iframe src="https://enfoldindia.github.io/first-love-map/" title="First Love Map of India" style="width:100%;height:100%;min-height:700px;border:0" loading="lazy" allow="fullscreen"></iframe>
```

Make the Embed element tall (at least 700 px) so the map and side panel are fully visible. You can also simply link to `https://enfoldindia.github.io/first-love-map/`.
