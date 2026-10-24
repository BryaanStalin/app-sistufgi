import assert from 'node:assert/strict';
import test from 'node:test';
import {
    examinationChanges,
    stockPreview,
} from '../../resources/scripts/oferta_flexible/laboratorio/toma_muestra_estado.js';

const pending = { id: 1, campania_servicio_id: 10, estado: 'P', editable: true };
const supply = { campania_servicio_id: 10, quedan: 9, tomadas: 0 };

test('changing a pending exam to realized preserves the reserved supply and increases taken count', () => {
    const selected = new Map([[1, 'T']]);
    assert.deepEqual(examinationChanges([pending], selected), [{ id: 1, estado: 'T', estado_original: 'P' }]);
    assert.deepEqual(stockPreview(supply, [pending], selected), { remaining: 9, taken: 1 });
});

test('no realizado returns the supply and returning to original state removes the unsaved change', () => {
    const selected = new Map([[1, 'N']]);
    assert.deepEqual(stockPreview(supply, [pending], selected), { remaining: 10, taken: 0 });
    selected.set(1, 'P');
    assert.deepEqual(examinationChanges([pending], selected), []);
    assert.deepEqual(stockPreview(supply, [pending], selected), { remaining: 9, taken: 0 });
});

test('correcting realized to pending lowers taken count while preserving consumption', () => {
    const realized = { ...pending, estado: 'T' };
    assert.deepEqual(stockPreview({ ...supply, tomadas: 1 }, [realized], new Map([[1, 'P']])), {
        remaining: 9, taken: 0,
    });
});

test('reactivating a no realizado exam reserves one supply', () => {
    const notDone = { ...pending, estado: 'N' };
    assert.deepEqual(stockPreview({ ...supply, quedan: 10 }, [notDone], new Map([[1, 'T']])), {
        remaining: 9, taken: 1,
    });
});

test('previous visits are readonly and never alter the stock projection of today', () => {
    const previous = { ...pending, editable: false };
    const selected = new Map([[1, 'N']]);
    assert.deepEqual(examinationChanges([previous], selected), []);
    assert.deepEqual(stockPreview(supply, [previous], selected), { remaining: 9, taken: 0 });
});

test('a different examination never alters another examinations supply or taken count', () => {
    assert.deepEqual(stockPreview({ ...supply, campania_servicio_id: 20 }, [pending], new Map([[1, 'T']])), {
        remaining: 9, taken: 0,
    });
});
