import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { runInNewContext } from 'node:vm';

const blade = readFileSync(new URL('../../resources/views/components/select-box.blade.php', import.meta.url), 'utf8');
const searchSelect = runInNewContext(blade.match(/<script>([\s\S]*?)<\/script>/)[1] + '\nsearchSelect;');
const persianDigits = '۰۱۲۳۴۵۶۷۸۹';

function makeSelector({ locale = 'fa', searchSubjectCode = true } = {}) {
    const component = searchSelect({ searchSubjectCode });
    const input = {
        value: '',
        selectionStart: 0,
        setSelectionRange(start) {
            this.selectionStart = start;
        },
    };
    const convertToEnglish = value => value.replace(/[۰-۹]/g, digit => persianDigits.indexOf(digit));
    const convertToFarsi = value => value.replace(/[0-9]/g, digit => persianDigits[digit]);
    component.$store = {
        utils: {
            convertToEnglish,
            convertToFarsi,
            formatCode: value => value.match(/.{1,3}/g)?.join('/') ?? '',
            localizeNumber: locale === 'fa' ? convertToFarsi : convertToEnglish,
        },
    };
    const searches = [];
    component.handleSearch = (...args) => searches.push(args);

    function edit(text, start = input.selectionStart, end = start) {
        input.value = input.value.slice(0, start) + text + input.value.slice(end);
        input.selectionStart = start + text.length;
        component.updateSubjectSearch(input);
    }

    return { component, input, searches, edit };
}

test('keeps the digit prefix and space when typing a customer or group name', () => {
    const options = [{ options: { 1: [{ groupName: '۵۶۷۸ همکاران', text: '۱۲۳۴ تجارت' }] } }];

    for (const name of ['1234 تجارت', '5678 همکاران']) {
        const { component, input, searches, edit } = makeSelector();
        for (const character of name.slice(0, 4)) edit(character);
        assert.match(input.value, /^[۰-۹]{3}\/[۰-۹]$/);
        assert.deepEqual(searches.at(-1), [name.slice(0, 4), true]);

        for (const character of name.slice(4)) edit(character);
        assert.equal(input.value, name);
        assert.deepEqual(searches.at(-1), [name, false]);
        assert.equal(component.filterOptions(name, options)[0].options[1][0].text, '۱۲۳۴ تجارت');
    }
});

test('preserves a typed slash and punctuation in a name', () => {
    const { input, searches, edit } = makeSelector({ locale: 'en' });
    for (const character of '1234/56, Branch') edit(character);

    assert.equal(input.value, '1234/56, Branch');
    assert.deepEqual(searches.at(-1), ['1234/56, branch', false]);
});

test('maps an edit within a displayed code to its original digits', () => {
    const { input, searches, edit } = makeSelector();
    edit('1234567');
    assert.equal(input.value, '۱۲۳/۴۵۶/۷');
    edit('', 4, 5);
    assert.equal(input.value, '۱۲۳/۵۶۷');
    assert.deepEqual(searches.at(-1), ['123567', true]);
    assert.equal(input.selectionStart, 3);
});

test('ordinary selectors retain literal text matching', () => {
    const { component } = makeSelector({ searchSubjectCode: false });

    assert.equal(component.matchingSearchQuery('۱۲۳۴ تجارت', '1234 تجارت'), null);
    assert.equal(component.matchingSearchQuery('۱۲۳۴ تجارت', 'تجارت'), 'تجارت');
});
