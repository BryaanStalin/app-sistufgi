<div class="card oferta-summary mt-4">
    <div
        class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3"
    >
        <div class="d-flex flex-wrap gap-4">
            <div>
                <span class="oferta-summary-number" data-summary-services>0</span>
                <span class="small text-body-secondary d-block">Servicios distintos</span>
            </div>
            <div>
                <span class="oferta-summary-number" data-summary-quotas>0</span>
                <span class="small text-body-secondary d-block">Cupos en todo el periodo
                </span>
            </div>
            <div data-summary-lab hidden>
                <span class="oferta-summary-number" data-summary-exams>0</span>
                <span class="small text-body-secondary d-block">Exámenes distintos</span>
            </div>
            <div data-summary-lab hidden>
                <span class="oferta-summary-number" data-summary-supplies>0</span>
                <span class="small text-body-secondary d-block">Insumos en todo el periodo</span>
            </div>
        </div>
        <div>
            <button
                type="submit"
                class="btn btn-primary"
                data-save-campania
                disabled
            >
                <i class="bi bi-check2-circle" aria-hidden="true"></i>
                {{ $textoGuardar }}
            </button>
            <p
                id="cupos-aviso"
                class="small text-danger mt-2 mb-0"
                role="status"
            >
                </p>
        </div>
    </div>
</div>
