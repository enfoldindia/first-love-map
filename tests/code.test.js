// Run with: node tests/code.test.js
const assert = require('assert');
const fs = require('fs');
const path = require('path');
const vm = require('vm');

// Load Code.gs as a plain script, as Apps Script does (no `module` in scope).
const src = fs.readFileSync(path.join(__dirname, '..', 'apps-script', 'Code.gs'), 'utf8');
const ctx = { console };
vm.createContext(ctx);
vm.runInContext(src, ctx);
const { roundCoord, validateSubmission, isApproved, publicStories } = vm.runInContext(
  '({roundCoord, validateSubmission, isApproved, publicStories})', ctx);

const good = () => ({ lat: 28.6139, lon: 77.209, story: 'A story.', year: '2008', website: '', openedAt: 1000, sentAt: 9000 });
let passed = 0;
const t = (name, fn) => { fn(); passed++; console.log('ok  ' + name); };
const err = (body) => { const r = validateSubmission(body); assert.strictEqual(r.ok, false); return r.error; };

t('valid submission accepted and rounded', () => {
  const r = validateSubmission(good());
  assert.strictEqual(r.ok, true);
  assert.deepStrictEqual(JSON.parse(JSON.stringify(r.clean)), { lat: 28.61, lon: 77.21, year: '2008', story: 'A story.' });
});
t('story is trimmed', () => assert.strictEqual(validateSubmission({ ...good(), story: '  hi  ' }).clean.story, 'hi'));
t('empty story rejected', () => assert.strictEqual(err({ ...good(), story: '' }), 'story_required'));
t('whitespace-only story rejected', () => assert.strictEqual(err({ ...good(), story: '   \n ' }), 'story_required'));
t('non-string story rejected', () => assert.strictEqual(err({ ...good(), story: 5 }), 'story_required'));
t('5000-char story accepted', () => assert.strictEqual(validateSubmission({ ...good(), story: 'a'.repeat(5000) }).ok, true));
t('5001-char story rejected', () => assert.strictEqual(err({ ...good(), story: 'a'.repeat(5001) }), 'story_too_long'));
t('20-char year accepted', () => assert.strictEqual(validateSubmission({ ...good(), year: 'y'.repeat(20) }).ok, true));
t('21-char year rejected', () => assert.strictEqual(err({ ...good(), year: 'y'.repeat(21) }), 'year_too_long'));
t('missing year becomes empty string', () => { const b = good(); delete b.year; assert.strictEqual(validateSubmission(b).clean.year, ''); });
t('outside India (London) rejected', () => assert.strictEqual(err({ ...good(), lat: 51.5, lon: -0.12 }), 'outside_india'));
t('outside India (lat too low) rejected', () => assert.strictEqual(err({ ...good(), lat: -10 }), 'outside_india'));
t('outside India (lon too high) rejected', () => assert.strictEqual(err({ ...good(), lon: 120 }), 'outside_india'));
t('Andaman edge accepted', () => assert.strictEqual(validateSubmission({ ...good(), lat: 7, lon: 93.8 }).ok, true));
t('non-numeric location rejected', () => assert.strictEqual(err({ ...good(), lat: '28.6' }), 'bad_location'));
t('NaN location rejected', () => assert.strictEqual(err({ ...good(), lon: NaN }), 'bad_location'));
t('honeypot filled rejected', () => assert.strictEqual(err({ ...good(), website: 'http://spam' }), 'rejected'));
t('submitted too fast (1s) rejected', () => assert.strictEqual(err({ ...good(), sentAt: 2000 }), 'too_fast'));
t('exactly 3s accepted', () => assert.strictEqual(validateSubmission({ ...good(), sentAt: 4000 }).ok, true));
t('missing timestamps rejected', () => { const b = good(); delete b.openedAt; assert.strictEqual(err(b), 'too_fast'); });
t('null body rejected', () => assert.strictEqual(err(null), 'bad_request'));
t('roundCoord 2 decimals', () => { assert.strictEqual(roundCoord(26.190388388, 2), 26.19); assert.strictEqual(roundCoord(78.134765625, 2), 78.13); });
t('roundCoord 0 decimals', () => assert.strictEqual(roundCoord(26.6, 0), 27));
t('isApproved handles boolean and TRUE string', () => {
  assert.ok(isApproved(true)); assert.ok(isApproved('TRUE')); assert.ok(isApproved('true'));
  assert.ok(!isApproved(false)); assert.ok(!isApproved('')); assert.ok(!isApproved('FALSE'));
});
t('publicStories returns only approved rows and public fields', () => {
  const rows = [
    [1, '2026-01-01T00:00:00Z', 10, 20, '2000', 'shown', true],
    [2, '2026-01-02T00:00:00Z', 11, 21, '2001', 'HIDDEN-SECRET', false],
    [3, '2026-01-03T00:00:00Z', 12, 22, 2002, 'imported', 'TRUE'],
    [4, '2026-01-04T00:00:00Z', 13, 23, '', 'also hidden', '']
  ];
  const out = JSON.parse(JSON.stringify(publicStories(rows)));
  assert.deepStrictEqual(out.map(s => s.id), [1, 3]);
  assert.deepStrictEqual(Object.keys(out[0]).sort(), ['createdAt', 'id', 'lat', 'lon', 'story', 'year']);
  assert.ok(!JSON.stringify(out).includes('HIDDEN-SECRET'));
  assert.strictEqual(out[1].year, '2002');
});
console.log(`\n${passed} tests passed`);
