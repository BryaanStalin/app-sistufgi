export function examinationChanges(examinations, selected) {
    return examinations
        .filter((exam) => exam.editable && selected.has(exam.id) && selected.get(exam.id) !== exam.estado)
        .map((exam) => ({ id: exam.id, estado: selected.get(exam.id), estado_original: exam.estado }));
}

export function stockPreview(row, examinations, selected) {
    let remaining = row.quedan;
    let taken = row.tomadas;
    for (const exam of examinations) {
        if (!exam.editable || exam.campania_servicio_id !== row.campania_servicio_id) {
            continue;
        }
        const state = selected.get(exam.id) ?? exam.estado;
        remaining += Number(exam.estado !== 'N') - Number(state !== 'N');
        taken += Number(state === 'T') - Number(exam.estado === 'T');
    }
    return { remaining: Math.max(0, remaining), taken: Math.max(0, taken) };
}
