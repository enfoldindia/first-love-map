(function () {
  'use strict';

  var MAX_STORY = 5000;
  var MAX_YEAR = 20;
  var PENDING_MESSAGE = 'Thank you — your memory will appear after review.';
  var LOCATION_BOUNDS = { latMin: 6, latMax: 38, lonMin: 67, lonMax: 98 };

  var $ = function (id) { return document.getElementById(id); };
  var connected = typeof APPS_SCRIPT_URL === 'string' && APPS_SCRIPT_URL !== '';

  var stories = [];
  var markers = {};
  var pin = null;          // { lat, lon }
  var pinMarker = null;
  var formOpenedAt = Date.now();
  var submitting = false;
  var toastTimer = null;

  function heartSvg(opacity) {
    return '<svg xmlns="http://www.w3.org/2000/svg" width="30" height="36" viewBox="0 0 30 36" style="overflow:visible">' +
      '<ellipse cx="15" cy="33" rx="5.5" ry="2" fill="rgba(0,0,0,0.13)" opacity="' + opacity + '"/>' +
      '<line x1="15" y1="23" x2="15" y2="31.5" stroke="#6d1a40" stroke-width="2" stroke-linecap="round" opacity="' + opacity + '"/>' +
      '<path d="M15,23 C15,23 4.5,15.5 4.5,9.5 C4.5,4.5 9.5,2.5 15,7 C20.5,2.5 25.5,4.5 25.5,9.5 C25.5,15.5 15,23 15,23Z" fill="#8b2252" stroke="rgba(255,255,255,0.9)" stroke-width="1.4" opacity="' + opacity + '"/></svg>';
  }

  function heartIcon(opacity) {
    return L.divIcon({ className: '', html: heartSvg(opacity), iconSize: [30, 36], iconAnchor: [15, 34], popupAnchor: [0, -32] });
  }

  function el(tag, cls, text) {
    var e = document.createElement(tag);
    if (cls) e.className = cls;
    if (text !== undefined) e.textContent = text;
    return e;
  }

  function showToast(message, ms) {
    var t = $('toast');
    t.textContent = message;
    t.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { t.classList.remove('show'); }, ms || 2800);
  }

  function setTab(name) {
    var share = name === 'share';
    $('tab-share').classList.toggle('active', share);
    $('tab-read').classList.toggle('active', !share);
    $('panel-share').hidden = !share;
    $('panel-read').hidden = share;
  }

  // ---- map ----
  var map = L.map('map', { center: [22.5, 80.5], zoom: 5, minZoom: 4, maxZoom: 18, zoomControl: true, attributionControl: true });
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    maxZoom: 19
  }).addTo(map);
  map.setMaxBounds(L.latLngBounds(L.latLng(5, 65), L.latLng(40, 100)).pad(0.3));

  map.on('click', function (e) {
    pin = { lat: e.latlng.lat, lon: e.latlng.lng };
    if (pinMarker) map.removeLayer(pinMarker);
    pinMarker = L.marker([pin.lat, pin.lon], { icon: heartIcon(0.4), interactive: false }).addTo(map);
    $('hint').classList.add('hidden');
    $('pin-badge').classList.add('active');
    $('pin-text').textContent = 'Location pinned ✓  (' + pin.lat.toFixed(2) + ', ' + pin.lon.toFixed(2) + ')';
    setTab('share');
    updateSubmit();
  });

  // ---- stories ----
  function popupContent(s) {
    var wrap = el('div');
    wrap.appendChild(el('div', 'flm-pop-place', '♥ A first love'));
    var text = s.story.length > 200 ? s.story.slice(0, 200) + '…' : s.story;
    wrap.appendChild(el('div', 'flm-pop-text', '“' + text + '”'));
    if (s.year) wrap.appendChild(el('div', 'flm-pop-meta', s.year));
    return wrap;
  }

  function addMarker(s) {
    var m = L.marker([s.lat, s.lon], { icon: heartIcon(1) }).addTo(map);
    m.bindPopup(popupContent(s), { maxWidth: 270, minWidth: 180 });
    m.on('mouseover', function () { this.openPopup(); });
    m.on('click', function () { focusCard(s.id); });
    markers[s.id] = m;
  }

  function focusCard(id) {
    setTab('read');
    var card = document.querySelector('[data-story-id="' + String(id).replace(/"/g, '') + '"]');
    if (!card) return;
    card.classList.add('highlighted');
    setTimeout(function () { card.classList.remove('highlighted'); }, 1600);
    setTimeout(function () { card.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); }, 80);
  }

  function flyTo(s) {
    map.flyTo([s.lat, s.lon], 11, { duration: 1.2 });
    var m = markers[s.id];
    if (m) setTimeout(function () { m.openPopup(); }, 1300);
  }

  function renderList() {
    var panel = $('panel-read');
    panel.textContent = '';
    $('count').textContent = String(stories.length);
    if (!stories.length) {
      var empty = el('div', 'flm-empty');
      empty.appendChild(document.createTextNode('No stories yet.'));
      empty.appendChild(document.createElement('br'));
      empty.appendChild(document.createTextNode('Be the first to pin a memory ♡'));
      panel.appendChild(empty);
      return;
    }
    stories.forEach(function (s) {
      var card = el('div', 'flm-card');
      card.setAttribute('data-story-id', String(s.id));
      card.appendChild(el('div', 'flm-card-loc', '♥ A first love'));
      card.appendChild(el('div', 'flm-card-text', s.story));
      if (s.year) card.appendChild(el('div', 'flm-card-meta', s.year));
      card.addEventListener('click', function () { flyTo(s); });
      panel.appendChild(card);
    });
  }

  function loadStories() {
    return fetch(APPS_SCRIPT_URL)
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!Array.isArray(data)) throw new Error('bad response');
        data.sort(function (a, b) { return String(b.createdAt).localeCompare(String(a.createdAt)); });
        stories = data.filter(function (s) {
          return s && typeof s.story === 'string' && isFinite(s.lat) && isFinite(s.lon);
        }).map(function (s) {
          return { id: s.id, lat: Number(s.lat), lon: Number(s.lon), story: s.story, year: s.year ? String(s.year) : '', createdAt: s.createdAt };
        });
        stories.forEach(addMarker);
        renderList();
      })
      .catch(function () {
        showToast("Couldn't load stories right now. Please try again later.", 4000);
      });
  }

  // ---- form ----
  function validate() {
    var story = $('story').value.trim();
    if (!pin) return 'Click the map to choose a location.';
    if (!story) return 'Please write your story.';
    if (story.length > MAX_STORY) return 'Your story is too long.';
    if (pin.lat < LOCATION_BOUNDS.latMin || pin.lat > LOCATION_BOUNDS.latMax ||
        pin.lon < LOCATION_BOUNDS.lonMin || pin.lon > LOCATION_BOUNDS.lonMax) {
      return 'Please choose a location within India.';
    }
    return '';
  }

  function updateSubmit() {
    var n = $('story').value.length;
    $('chars').textContent = n.toLocaleString('en-US') + ' / 5,000';
    $('chars').classList.toggle('over', n > MAX_STORY);
    $('submit').disabled = !connected || submitting || !pin || !$('story').value.trim();
  }

  function submit() {
    var err = validate();
    $('form-error').textContent = err;
    if (err || submitting) return;
    submitting = true;
    updateSubmit();
    var body = {
      lat: pin.lat,
      lon: pin.lon,
      story: $('story').value.trim(),
      year: $('year').value.trim().slice(0, MAX_YEAR),
      website: $('website').value,
      openedAt: formOpenedAt,
      sentAt: Date.now()
    };
    // text/plain keeps this a "simple" request so the browser sends no CORS preflight.
    fetch(APPS_SCRIPT_URL, { method: 'POST', headers: { 'Content-Type': 'text/plain;charset=utf-8' }, body: JSON.stringify(body) })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res || !res.ok) throw new Error((res && res.error) || 'failed');
        if (pinMarker) { map.removeLayer(pinMarker); pinMarker = null; }
        pin = null;
        $('story').value = '';
        $('year').value = '';
        $('pin-badge').classList.remove('active');
        $('pin-text').textContent = 'No location yet — click the map';
        formOpenedAt = Date.now();
        $('form-error').textContent = '';
        showToast(res.pending ? PENDING_MESSAGE : 'Your memory has been pinned ♡', 5000);
        if (!res.pending) loadStoriesAfterPublish();
      })
      .catch(function (e) {
        $('form-error').textContent = 'Something went wrong (' + (e && e.message ? e.message : 'error') + '). Please try again in a moment.';
      })
      .then(function () { submitting = false; updateSubmit(); });
  }

  function loadStoriesAfterPublish() {
    Object.keys(markers).forEach(function (k) { map.removeLayer(markers[k]); });
    markers = {};
    loadStories();
  }

  $('story').addEventListener('input', updateSubmit);
  $('tab-share').addEventListener('click', function () { setTab('share'); });
  $('tab-read').addEventListener('click', function () { setTab('read'); });
  $('submit').addEventListener('click', submit);

  renderList();
  updateSubmit();
  if (connected) {
    loadStories();
  } else {
    $('notice').hidden = false;
    $('hint').textContent = 'This map is not connected yet';
    $('panel-read').textContent = '';
    var msg = el('div', 'flm-empty', 'Stories will appear here once the map is connected.');
    $('panel-read').appendChild(msg);
  }
  setTimeout(function () { map.invalidateSize(); }, 100);
})();
