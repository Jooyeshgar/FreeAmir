import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';
import { test } from 'node:test';

// Execute the actual Alpine component, substituting only Blade's server data.
const source = readFileSync(new URL('../../resources/views/ancillaryCosts/form.blade.php', import.meta.url), 'utf8');
const script = source.slice(source.indexOf("document.addEventListener('alpine:init'"), source.lastIndexOf('</script>'))
    .replace(/@json\(old\('allocation_amount', 0\)\)/g, '0')
    .replace(/@json\(old\('ancillaryCosts', \$ancillaryCostItems \?\? \[\]\)\)/g, '__existingCosts')
    .replace(/\{\{[^]*?\}\}/g, 'null');

function form(existingCosts = []) {
    let factory;
    vm.runInNewContext(script, {
        document: { addEventListener: (_, callback) => callback() },
        Alpine: { data: (_, callback) => { factory = callback; } },
        __existingCosts: existingCosts,
    });
    const component = factory();
    component.$store = { utils: { convertToEnglish: value => String(value ?? '').replace(/,/g, '')
        .replace(/[۰-۹]/g, digit => '۰۱۲۳۴۵۶۷۸۹'.indexOf(digit)) } };
    component.availableProducts = [
        { id: 1, name: 'First', quantity: 2.5, value: 300, weight: 2 },
        { id: 2, name: 'Second', quantity: 5, value: 100, weight: 3 },
        { id: 3, name: 'Unweighed', quantity: 1, value: 100, weight: 0 },
    ];
    return component;
}

function amounts(component) { return Array.from(component.availableProducts, product => component.productAmounts[product.id]); }

test('Manual is the default and editing restores saved amounts including zero', () => {
    const f = form([{ product_id: 1, amount: 123.45 }, { product_id: 2, amount: 0 }]);
    f.init();
    assert.equal(f.allocationMethod, 'manual');
    assert.equal(f.productAmounts[1], 123.45);
    f.updateProductAmount(2, '۱۲.۵');
    assert.equal(f.calculateTotal(), 135.95);
    f.allocate();
    assert.equal(f.productAmounts[1], 123.45);
});

test('Value shares are proportional to the supplied pre-VAT discounted values', () => {
    const f = form();
    f.allocationMethod = 'value';
    f.allocationAmount = '۱,۰۰۰';
    f.allocate();
    assert.deepEqual(amounts(f), [600, 200, 200]);
    assert.equal(f.calculateTotalWithVat(10), 1100);
});

test('Weight uses fractional quantity times unit weight and excludes zero weights', () => {
    const f = form();
    f.allocationMethod = 'weight';
    f.allocationAmount = 100;
    f.allocate();
    assert.deepEqual(amounts(f), [25, 75, 0]);
    assert.equal(f.zeroWeightProducts[0].name, 'Unweighed');
});

test('Changing methods or the entered total recalculates shares; Manual stays editable', () => {
    const f = form();
    f.allocationAmount = 100;
    f.allocationMethod = 'value';
    f.changeAllocationMethod();
    assert.deepEqual(amounts(f), [60, 20, 20]);
    f.allocationMethod = 'weight';
    f.changeAllocationMethod();
    assert.deepEqual(amounts(f), [25, 75, 0]);
    f.allocationAmount = 200;
    f.allocate();
    assert.deepEqual(amounts(f), [50, 150, 0]);
    f.allocationMethod = 'manual';
    f.updateProductAmount(1, 55);
    f.allocate();
    assert.equal(f.calculateTotal(), 205);
});

test('Rounding preserves the total to two decimal places and never assigns zero-weight products a share', () => {
    const f = form();
    f.availableProducts.forEach(p => { p.value = 1; });
    f.allocationMethod = 'value';
    f.allocationAmount = 0.01;
    f.allocate();
    assert.deepEqual(amounts(f), [0, 0.01, 0]);
    assert.equal(f.calculateTotal(), 0.01);
});

test('No positive basis produces zero shares without NaN or infinity', () => {
    const f = form();
    f.availableProducts.forEach(p => { p.weight = 0; p.value = 0; });
    f.allocationAmount = 100;
    for (const method of ['weight', 'value']) {
        f.allocationMethod = method;
        f.allocate();
        assert.deepEqual(amounts(f), [0, 0, 0]);
        assert.equal(f.allocationBasisTotal, 0);
    }
});

test('Calculated amount inputs are read-only and submitted, and both warning conditions are present', () => {
    assert.match(source, /x-bind:readonly="allocationMethod !== 'manual'"/);
    assert.match(source, /x-bind:name="'ancillaryCosts\[' \+ index \+ '\]\[amount\]'/);
    assert.match(source, /allocationMethod === 'weight' && zeroWeightProducts.length/);
    assert.match(source, /!allocationBasisTotal/);
});
