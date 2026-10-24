export function buttonState({ busy, editing, hasPatient, hasAttention, hasChanges, validFields, hasSelectedExam }) {
    const canEdit = !busy && editing && hasPatient;

    return {
        save: canEdit && hasChanges && validFields,
        modify: !busy && !editing && hasAttention,
        newPatient: !busy,
        remove: !busy && !editing && hasAttention,
        add: canEdit && hasSelectedExam,
    };
}
