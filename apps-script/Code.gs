/**
 * First Love Map of India - backend.
 *
 * A Google Apps Script web app bound to a private Google Sheet. The Sheet is
 * the moderation interface: tick "approved" to publish a story, delete the row
 * to remove it.
 *
 * Sheet columns (row 1 is the header):
 *   id | createdAt | lat | lon | year | story | approved
 */

// When true, new submissions are saved with approved = FALSE and stay hidden
// until a moderator ticks the checkbox in the Sheet. When false, submissions
// are published immediately.
var PREMODERATION = true;

// Decimal places kept for lat/lon before storing. 2 decimals is about 1.1 km,
// so a pin marks an area rather than an exact address.
var LOCATION_DECIMALS = 2;

// Maximum story length in characters, measured after trimming whitespace.
var MAX_STORY_LENGTH = 5000;

// Maximum length of the free-text year field.
var MAX_YEAR_LENGTH = 20;

// Generous bounding box around India (degrees). Submissions outside it are rejected.
var INDIA_BOUNDS = { latMin: 6, latMax: 38, lonMin: 67, lonMax: 98 };

// Minimum time the form must have been open before a submission is accepted.
// Bots that post instantly are rejected.
var MIN_FORM_OPEN_MS = 3000;

// How long the public story list is cached, in seconds.
var CACHE_SECONDS = 60;

var CACHE_KEY = 'stories-v1';
var HEADERS = ['id', 'createdAt', 'lat', 'lon', 'year', 'story', 'approved'];

// ---------------------------------------------------------------------------
// Pure functions (no Apps Script services; covered by tests/code.test.js)
// ---------------------------------------------------------------------------

/** Rounds a number to the given number of decimal places. */
function roundCoord(value, decimals) {
  var f = Math.pow(10, decimals);
  return Math.round(value * f) / f;
}

function isFiniteNumber(v) {
  return typeof v === 'number' && isFinite(v);
}

/**
 * Validates a submission body.
 * Returns { ok: true, clean: {lat, lon, year, story} } or { ok: false, error }.
 * `body` is the parsed JSON object; the form timestamps (openedAt, sentAt) are
 * both client clock values, so their difference is immune to clock skew.
 */
function validateSubmission(body) {
  if (!body || typeof body !== 'object') return { ok: false, error: 'bad_request' };

  if (body.website !== undefined && body.website !== null && String(body.website) !== '') {
    return { ok: false, error: 'rejected' };
  }

  if (!isFiniteNumber(body.openedAt) || !isFiniteNumber(body.sentAt) ||
      body.sentAt - body.openedAt < MIN_FORM_OPEN_MS) {
    return { ok: false, error: 'too_fast' };
  }

  if (typeof body.story !== 'string') return { ok: false, error: 'story_required' };
  var story = body.story.trim();
  if (story.length < 1) return { ok: false, error: 'story_required' };
  if (story.length > MAX_STORY_LENGTH) return { ok: false, error: 'story_too_long' };

  var year = body.year === undefined || body.year === null ? '' : body.year;
  if (typeof year !== 'string' && typeof year !== 'number') return { ok: false, error: 'bad_year' };
  year = String(year).trim();
  if (year.length > MAX_YEAR_LENGTH) return { ok: false, error: 'year_too_long' };

  if (!isFiniteNumber(body.lat) || !isFiniteNumber(body.lon)) {
    return { ok: false, error: 'bad_location' };
  }
  if (body.lat < INDIA_BOUNDS.latMin || body.lat > INDIA_BOUNDS.latMax ||
      body.lon < INDIA_BOUNDS.lonMin || body.lon > INDIA_BOUNDS.lonMax) {
    return { ok: false, error: 'outside_india' };
  }

  return {
    ok: true,
    clean: {
      lat: roundCoord(body.lat, LOCATION_DECIMALS),
      lon: roundCoord(body.lon, LOCATION_DECIMALS),
      year: year,
      story: story
    }
  };
}

/** True for a ticked checkbox (boolean true) or an imported "TRUE" string. */
function isApproved(value) {
  return value === true || String(value).toUpperCase() === 'TRUE';
}

/**
 * Converts sheet rows (without the header) into the public story list.
 * Only approved rows are included, and only the public fields are copied.
 */
function publicStories(rows) {
  var out = [];
  for (var i = 0; i < rows.length; i++) {
    var r = rows[i];
    if (!isApproved(r[6])) continue;
    var story = String(r[5]);
    if (story.trim() === '') continue;
    var created = r[1] instanceof Date ? r[1].toISOString() : String(r[1]);
    out.push({
      id: r[0],
      lat: Number(r[2]),
      lon: Number(r[3]),
      year: String(r[4]),
      story: story,
      createdAt: created
    });
  }
  return out;
}

// ---------------------------------------------------------------------------
// Web app entry points
// ---------------------------------------------------------------------------

function jsonOutput(obj) {
  return ContentService.createTextOutput(JSON.stringify(obj))
    .setMimeType(ContentService.MimeType.JSON);
}

function getSheet() {
  return SpreadsheetApp.getActiveSpreadsheet().getSheets()[0];
}

function doGet() {
  var cache = CacheService.getScriptCache();
  var cached = cache.get(CACHE_KEY);
  if (cached) {
    return ContentService.createTextOutput(cached).setMimeType(ContentService.MimeType.JSON);
  }
  var sheet = getSheet();
  var last = sheet.getLastRow();
  var rows = last > 1 ? sheet.getRange(2, 1, last - 1, HEADERS.length).getValues() : [];
  var json = JSON.stringify(publicStories(rows));
  // The cache holds values up to 100 KB; skip caching if the list outgrows that.
  if (json.length < 90000) cache.put(CACHE_KEY, json, CACHE_SECONDS);
  return ContentService.createTextOutput(json).setMimeType(ContentService.MimeType.JSON);
}

function doPost(e) {
  var body;
  try {
    body = JSON.parse(e.postData.contents);
  } catch (err) {
    return jsonOutput({ ok: false, error: 'bad_request' });
  }
  var result = validateSubmission(body);
  if (!result.ok) return jsonOutput({ ok: false, error: result.error });

  var lock = LockService.getScriptLock();
  lock.waitLock(10000);
  try {
    var sheet = getSheet();
    var row = sheet.getLastRow() + 1;
    var c = result.clean;
    // Story and year are stored as plain text so a value like "=1+1" is never
    // evaluated as a formula.
    sheet.getRange(row, 5, 1, 2).setNumberFormat('@');
    sheet.getRange(row, 1, 1, HEADERS.length).setValues([[
      Date.now(), new Date().toISOString(), c.lat, c.lon, c.year, c.story, !PREMODERATION
    ]]);
    sheet.getRange(row, 7).insertCheckboxes();
    sheet.getRange(row, 7).setValue(!PREMODERATION);
    CacheService.getScriptCache().remove(CACHE_KEY);
  } finally {
    lock.releaseLock();
  }
  return jsonOutput({ ok: true, pending: PREMODERATION });
}

if (typeof module !== 'undefined' && module.exports) {
  module.exports = {
    roundCoord: roundCoord,
    validateSubmission: validateSubmission,
    isApproved: isApproved,
    publicStories: publicStories,
    constants: {
      PREMODERATION: PREMODERATION, LOCATION_DECIMALS: LOCATION_DECIMALS,
      MAX_STORY_LENGTH: MAX_STORY_LENGTH, MAX_YEAR_LENGTH: MAX_YEAR_LENGTH,
      MIN_FORM_OPEN_MS: MIN_FORM_OPEN_MS
    }
  };
}
