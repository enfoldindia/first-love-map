/* First Love Map front end. All user-supplied text is rendered with textContent. */
(function () {
  'use strict';

  var cfg = window.FLM_CONFIG;
  if (!cfg || !window.L) return;

  var MAX_STORY = 5000;
  var MAX_YEAR = 20;
  var PENDING_MESSAGE = 'Thank you — your memory will appear after review.';
  var BOUNDS = { latMin: 6, latMax: 38, lonMin: 67, lonMax: 98 };

  function el(tag, cls, text) {
    var e = document.createElement(tag);
    if (cls) e.className = cls;
    if (text !== undefined) e.textContent = text;
    return e;
  }

  function heartSvg(opacity) {
    var ns = 'http://www.w3.org/2000/svg';
    var svg = document.createElementNS(ns, 'svg');
    svg.setAttribute('width', '30');
    svg.setAttribute('height', '36');
    svg.setAttribute('viewBox', '0 0 30 36');
    svg.style.overflow = 'visible';
    function node(name, attrs) {
      var n = document.createElementNS(ns, name);
      Object.keys(attrs).forEach(function (k) { n.setAttribute(k, attrs[k]); });
      svg.appendChild(n);
    }
    node('ellipse', { cx: 15, cy: 33, rx: 5.5, ry: 2, fill: 'rgba(0,0,0,0.13)', opacity: opacity });
    node('line', { x1: 15, y1: 23, x2: 15, y2: 31.5, stroke: '#6d1a40', 'stroke-width': 2, 'stroke-linecap': 'round', opacity: opacity });
    node('path', { d: 'M15,23 C15,23 4.5,15.5 4.5,9.5 C4.5,4.5 9.5,2.5 15,7 C20.5,2.5 25.5,4.5 25.5,9.5 C25.5,15.5 15,23 15,23Z', fill: '#8b2252', stroke: 'rgba(255,255,255,0.9)', 'stroke-width': 1.4, opacity: opacity });
    return svg;
  }

  function heartIcon(opacity) {
    var holder = document.createElement('div');
    holder.appendChild(heartSvg(opacity));
    return L.divIcon({ className: '', html: holder.firstChild, iconSize: [30, 36], iconAnchor: [15, 34], popupAnchor: [0, -32] });
  }

  function init(root) {
    if (root.getAttribute('data-flm-ready')) return;
    root.setAttribute('data-flm-ready', '1');

    var embed = root.classList.contains('flm-embed');
    var q = function (name) { return root.querySelector('[data-flm="' + name + '"]'); };

    var stories = [];
    var markers = {};
    var pin = null;
    var pinMarker = null;
    var formOpenedAt = Date.now();
    var submitting = false;
    var toastTimer = null;

    function showToast(message, ms) {
      var t = q('toast');
      t.textContent = message;
      t.classList.add('show');
      clearTimeout(toastTimer);
      toastTimer = setTimeout(function () { t.classList.remove('show'); }, ms || 2800);
    }

    function setTab(name) {
      var share = name === 'share';
      q('tab-share').classList.toggle('active', share);
      q('tab-read').classList.toggle('active', !share);
      q('panel-share').hidden = !share;
      q('panel-read').hidden = share;
    }

    var map = L.map(q('map'), {
      center: [22.5, 80.5], zoom: 5, minZoom: 4, maxZoom: 18,
      zoomControl: true, attributionControl: true, scrollWheelZoom: embed
    });
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
      maxZoom: 19
    }).addTo(map);
    map.setMaxBounds(L.latLngBounds(L.latLng(5, 65), L.latLng(40, 100)).pad(0.3));
    if (!embed) {
      // Keep page scrolling intact until the visitor interacts with the map.
      map.on('focus', function () { map.scrollWheelZoom.enable(); });
      map.on('blur', function () { map.scrollWheelZoom.disable(); });
    }

    map.on('click', function (e) {
      pin = { lat: e.latlng.lat, lon: e.latlng.lng };
      if (pinMarker) map.removeLayer(pinMarker);
      pinMarker = L.marker([pin.lat, pin.lon], { icon: heartIcon(0.4), interactive: false }).addTo(map);
      q('hint').classList.add('hidden');
      q('pin-badge').classList.add('active');
      q('pin-text').textContent = 'Location pinned ✓  (' + pin.lat.toFixed(2) + ', ' + pin.lon.toFixed(2) + ')';
      setTab('share');
      updateSubmit();
    });

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
      var cards = q('panel-read').children;
      for (var i = 0; i < cards.length; i++) {
        if (cards[i].getAttribute('data-story-id') === String(id)) {
          var card = cards[i];
          card.classList.add('highlighted');
          setTimeout(function () { card.classList.remove('highlighted'); }, 1600);
          setTimeout(function () { card.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); }, 80);
          return;
        }
      }
    }

    function flyTo(s) {
      map.flyTo([s.lat, s.lon], 11, { duration: 1.2 });
      var m = markers[s.id];
      if (m) setTimeout(function () { m.openPopup(); }, 1300);
    }

    function renderList() {
      var panel = q('panel-read');
      panel.textContent = '';
      q('count').textContent = String(stories.length);
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
      return fetch(cfg.restUrl, { headers: { Accept: 'application/json' } })
        .then(function (r) { if (!r.ok) throw new Error('bad status'); return r.json(); })
        .then(function (data) {
          if (!Array.isArray(data)) throw new Error('bad response');
          data.sort(function (a, b) { return String(b.createdAt).localeCompare(String(a.createdAt)); });
          Object.keys(markers).forEach(function (k) { map.removeLayer(markers[k]); });
          markers = {};
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

    function validate() {
      var story = q('story').value.trim();
      if (!pin) return 'Click the map to choose a location.';
      if (!story) return 'Please write your story.';
      if (story.length > MAX_STORY) return 'Your story is too long.';
      if (pin.lat < BOUNDS.latMin || pin.lat > BOUNDS.latMax || pin.lon < BOUNDS.lonMin || pin.lon > BOUNDS.lonMax) {
        return 'Please choose a location within India.';
      }
      return '';
    }

    function updateSubmit() {
      var n = q('story').value.length;
      q('chars').textContent = n.toLocaleString('en-US') + ' / 5,000';
      q('chars').classList.toggle('over', n > MAX_STORY);
      q('submit').disabled = submitting || !pin || !q('story').value.trim();
    }

    function submit() {
      var err = validate();
      q('form-error').textContent = err;
      if (err || submitting) return;
      submitting = true;
      updateSubmit();
      var body = {
        lat: pin.lat,
        lon: pin.lon,
        story: q('story').value.trim(),
        year: q('year').value.trim().slice(0, MAX_YEAR),
        website: q('website').value,
        openedAt: formOpenedAt,
        sentAt: Date.now()
      };
      fetch(cfg.restUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify(body)
      })
        .then(function (r) {
          return r.json().catch(function () { return {}; }).then(function (j) {
            if (!r.ok || !j || !j.ok) throw new Error((j && j.message) || 'Something went wrong.');
            return j;
          });
        })
        .then(function (res) {
          if (pinMarker) { map.removeLayer(pinMarker); pinMarker = null; }
          pin = null;
          q('story').value = '';
          q('year').value = '';
          q('pin-badge').classList.remove('active');
          q('pin-text').textContent = 'No location yet — click the map';
          formOpenedAt = Date.now();
          q('form-error').textContent = '';
          showToast(res.pending ? PENDING_MESSAGE : 'Your memory has been pinned ♡', 5000);
          if (!res.pending) loadStories();
        })
        .catch(function (e) {
          q('form-error').textContent = (e && e.message ? e.message : 'Something went wrong.') + ' Please try again in a moment.';
        })
        .then(function () { submitting = false; updateSubmit(); });
    }

    q('story').addEventListener('input', updateSubmit);
    q('tab-share').addEventListener('click', function () { setTab('share'); });
    q('tab-read').addEventListener('click', function () { setTab('read'); });
    q('submit').addEventListener('click', submit);

    renderList();
    updateSubmit();
    loadStories();
    setTimeout(function () { map.invalidateSize(); }, 100);
  }

  function initAll() {
    var roots = document.querySelectorAll('[data-flm-root]');
    for (var i = 0; i < roots.length; i++) init(roots[i]);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAll);
  } else {
    initAll();
  }
})();
