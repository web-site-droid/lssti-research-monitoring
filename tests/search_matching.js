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
const rankStart = app.indexOf('    function suggestionRank(node, query) {');
const rankEnd = app.indexOf('    function sortNodes(list) {', rankStart);
vm.runInContext(app.slice(rankStart, rankEnd), state);
const suggestions = [paper('The impact of technology', 'David', ''), paper('The development of a system', 'Jane', ''), paper('Design of a system', 'Jane', '')];
suggestions.sort((a,b) => state.suggestionRank(a, 'd') - state.suggestionRank(b, 'd'));
assert.strictEqual(suggestions[0].getAttribute('data-search-title'), 'Design of a system');
assert.strictEqual(suggestions[1].getAttribute('data-search-title'), 'The development of a system');
assert.strictEqual(suggestions[2].getAttribute('data-search-title'), 'The impact of technology');
console.log('Passed 3 title-prefix suggestion ranking checks.');
state.queryEl.value = 'd';
assert.strictEqual(state.matches(paper('Design of a system', 'Jane', '')), true);
assert.strictEqual(state.matches(paper('The development of a system', 'David', '')), false);
assert.strictEqual(state.matches(paper('Artificial Intelligence', 'David', '')), false);
console.log('Passed 3 single-letter prefix checks.');
