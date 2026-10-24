import test from 'node:test';
import assert from 'node:assert/strict';
import { buttonState } from '../../resources/scripts/oferta_flexible/laboratorio/admision_estado.js';

const editing = {
    busy: false,
    editing: true,
    hasPatient: true,
    hasAttention: false,
    hasChanges: true,
    validFields: true,
    hasSelectedExam: true,
};

test('save requires an identified patient, complete fields and examination changes', () => {
    assert.equal(buttonState(editing).save, true);
    for (const field of ['hasPatient', 'hasChanges', 'validFields']) {
        assert.equal(buttonState({ ...editing, [field]: false }).save, false);
    }
});

test('saved mode enables Modify New Patient and registry deletion', () => {
    assert.deepEqual(buttonState({ ...editing, editing: false, hasAttention: true, hasChanges: false }), {
        save: false,
        modify: true,
        newPatient: true,
        remove: true,
        add: false,
    });
});

test('Modify enables saving only pending removals even without new examinations', () => {
    assert.equal(buttonState({ ...editing, hasAttention: true, hasChanges: true }).save, true);
    assert.equal(buttonState({ ...editing, hasAttention: true, hasChanges: false }).save, false);
});

test('an AJAX operation disables every action to prevent double submission', () => {
    assert.deepEqual(buttonState({ ...editing, busy: true, hasAttention: true }), {
        save: false,
        modify: false,
        newPatient: false,
        remove: false,
        add: false,
    });
});

test('an unavailable examination cannot enable Add', () => {
    assert.equal(buttonState({ ...editing, hasSelectedExam: false }).add, false);
    assert.equal(buttonState({ ...editing, hasPatient: false }).add, false);
});

test('registry deletion is available only for a saved attention and recovers after confirmation is cancelled', () => {
    const saved = { ...editing, editing: false, hasAttention: true };
    assert.equal(buttonState(saved).remove, true);
    assert.equal(buttonState({ ...saved, busy: true }).remove, false);
    assert.equal(buttonState({ ...saved, busy: false }).remove, true);
    assert.equal(buttonState({ ...saved, editing: true }).remove, false);
    assert.equal(buttonState({ ...saved, hasAttention: false }).remove, false);
});
