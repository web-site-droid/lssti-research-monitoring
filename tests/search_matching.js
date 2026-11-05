const fs = require('fs');
const vm = require('vm');
const assert = require('assert');
const app = fs.readFileSync(__dirname + '/../public/assets/js/app.js', 'utf8');
const source = app.slice(app.indexOf('    function matches(node) {'), app.indexOf('    function showHits(pool) {'));
const state = {queryEl: {value: 'the'}, applied: {}, statusAttr: 'data-gate', schoolYearOk: () => true};
vm.createContext(state); vm.runInContext(source, state);
function paper(title, authors, abstract) {
  return {
    textContent: title + ' ' + authors + ' ' + abstract + ' Read the whole file',
    getAttribute: key => ({'data-search-title': title, 'data-search-authors': authors, 'data-program': 'BSCS'}[key] || ''),
    querySelector: selector => selector === 'h3, td' ? {textContent: title} : {textContent: authors},
    querySelectorAll: () => []
  };
}
assert.strictEqual(state.matches(paper('Artificial Intelligence', 'Jane Doe', 'The research discusses the system')), false);
assert.strictEqual(state.matches(paper('The Impact of Technology', 'Jane Doe', '')), true);
state.queryEl.value = 'THE IMPACT';
assert.strictEqual(state.matches(paper('The Impact of Technology', 'Jane Doe', '')), true);
assert.strictEqual(state.matches(paper('Impact of Technology', 'Jane Doe', 'The research')), false);
state.queryEl.value = 'Jane';
assert.strictEqual(state.matches(paper('Artificial Intelligence', 'Jane Doe', '')), true);
state.queryEl.value = 'BSCS';
assert.strictEqual(state.matches(paper('Artificial Intelligence', 'Jane Doe', '')), true);
state.queryEl.value = '%';
assert.strictEqual(state.matches(paper('Artificial Intelligence', 'Jane Doe', '')), false);
state.queryEl.value = '';
assert.strictEqual(state.matches(paper('Artificial Intelligence', 'Jane Doe', '')), true);
console.log('Passed 8 dashboard search matching checks.');
