@extends('layouts.app')

@section('title', 'POS — Retiro en sitio')

@section('content')
<div class="max-w-2xl mx-auto">
    <x-card>
        <label class="block text-sm font-medium mb-1">Evento</label>
        <select id="evento_id" class="w-full text-lg border border-slate-300 rounded-md px-3 py-2.5">
            <option value="">— Elegí un evento —</option>
            @foreach($eventos as $evento)
                <option value="{{ $evento->evento_id }}">{{ $evento->evento_id }} — {{ $evento->evento_nombre ?? 'sin nombre' }}</option>
            @endforeach
        </select>
        <label class="block text-sm font-medium mt-3 mb-1">Nombre de quien entrega (queda en el registro, opcional)</label>
        <input type="text" id="entregado_por" class="w-full border border-slate-300 rounded-md px-3 py-2">
    </x-card>

    <x-card>
        <input type="text" id="q" placeholder="Buscar por documento, apellido o referencia..." autocomplete="off"
            class="w-full text-xl px-4 py-4 rounded-lg border-2 border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-600 focus:border-brand-600">
        <div id="resultados" class="mt-3 space-y-3"></div>
    </x-card>
</div>

<script>
const eventoSelect = document.getElementById('evento_id');
const qInput = document.getElementById('q');
const entregadoPorInput = document.getElementById('entregado_por');
const resultadosEl = document.getElementById('resultados');
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

const params = new URLSearchParams(location.search);
if (params.get('evento_id')) eventoSelect.value = params.get('evento_id');
entregadoPorInput.value = localStorage.getItem('pos_entregado_por') || '';
entregadoPorInput.addEventListener('input', () => localStorage.setItem('pos_entregado_por', entregadoPorInput.value));

let timer = null;
qInput.addEventListener('input', () => {
    clearTimeout(timer);
    timer = setTimeout(buscar, 300);
});
eventoSelect.addEventListener('change', buscar);

async function buscar() {
    const eventoId = eventoSelect.value;
    const q = qInput.value.trim();
    if (!eventoId || q.length < 2) {
        resultadosEl.innerHTML = '';
        return;
    }
    const url = `{{ route('pos.buscar') }}?evento_id=${encodeURIComponent(eventoId)}&q=${encodeURIComponent(q)}`;
    const res = await fetch(url);
    const items = await res.json();
    render(items);
}

function render(items) {
    if (!items.length) {
        resultadosEl.innerHTML = '<div class="text-center text-slate-500 py-8 text-base">Sin resultados.</div>';
        return;
    }
    resultadosEl.innerHTML = items.map(r => {
        const entregado = r.estado === 'entregado';
        // Cobro en sitio (12/08/2026) — ver
        // ApiRestEvent/brain/api_rest_event/PRD-precios-periodos-fechas.md,
        // sección 0. `confirmar_pago_sitio_url` solo viene poblado por el
        // sync para form_types sin categoría pendientes de pago — el resto
        // de los pendientes (con categoría, o QR en curso) ni siquiera
        // llega acá, el sync ya los descarta.
        const pendienteDeCobro = !entregado && r.pago_status !== 'paid' && !!r.confirmar_pago_sitio_url;
        const cardClass = entregado
            ? 'bg-green-50 border-green-200'
            : pendienteDeCobro ? 'bg-amber-50 border-amber-300' : 'bg-white border-slate-200';
        const btn = entregado
            ? `<button onclick="deshacer(${r.id})" class="text-lg px-6 py-3.5 rounded-lg whitespace-nowrap bg-slate-200 hover:bg-slate-300 text-slate-800">Deshacer</button>`
            : pendienteDeCobro
                ? `<button onclick="entregar(${r.id})" class="text-lg px-6 py-3.5 rounded-lg whitespace-nowrap bg-amber-600 hover:bg-amber-700 text-white">Cobrar Bs ${escapeHtml(r.monto ?? '?')} y confirmar entrega</button>`
                : `<button onclick="entregar(${r.id})" class="text-lg px-6 py-3.5 rounded-lg whitespace-nowrap bg-brand-600 hover:bg-brand-700 text-white">Confirmar entrega</button>`;

        // Numeración: si ya vino cargada (el proveedor llegó a tiempo para
        // esta persona), se muestra de solo lectura — el proveedor no
        // llegó a tiempo es la excepción, no el default. Si falta, se
        // ofrecen inputs para cargarla a mano al momento de entregar.
        const tieneNumeracion = r.numero_corredor && r.chip;
        const numeracionHtml = entregado ? '' : (tieneNumeracion
            ? `<div class="text-sm text-slate-600 mt-2">N° corredor: <strong>${escapeHtml(r.numero_corredor)}</strong> · Chip: <strong>${escapeHtml(r.chip)}</strong></div>`
            : `<div class="mt-2 flex gap-2">
                <input type="text" id="numero_corredor-${r.id}" placeholder="N° corredor" value="${escapeHtml(r.numero_corredor || '')}"
                    class="w-28 border border-slate-300 rounded-md px-2 py-1.5 text-sm">
                <input type="text" id="chip-${r.id}" placeholder="Chip" value="${escapeHtml(r.chip || '')}"
                    class="w-28 border border-slate-300 rounded-md px-2 py-1.5 text-sm">
               </div>`);

        return `
        <div id="retiro-${r.id}" class="border rounded-xl px-5 py-4 flex justify-between items-center gap-3 ${cardClass}">
            <div>
                <div class="text-xl font-bold">${escapeHtml(r.nombre || '')} ${escapeHtml(r.apellido || '')}</div>
                <div class="text-sm text-slate-600 mt-1">
                    Doc: ${escapeHtml(r.documento || '—')} · Categoría: ${escapeHtml(r.categoria || '—')} ·
                    Talla: ${escapeHtml(r.talla || '—')} ${r.souvenirs ? '· ' + escapeHtml(r.souvenirs) : ''}
                    <br>Ref: ${escapeHtml(r.referencia || '—')}
                    ${pendienteDeCobro ? '<br><strong class="text-amber-700">Pendiente de pago — Bs ' + escapeHtml(r.monto ?? '?') + '</strong>' : ''}
                    ${entregado ? '<br><strong>Entregado' + (r.entregado_por ? ' por ' + escapeHtml(r.entregado_por) : '') + '</strong>' : ''}
                    ${entregado && tieneNumeracion ? '<br>N° corredor: <strong>' + escapeHtml(r.numero_corredor) + '</strong> · Chip: <strong>' + escapeHtml(r.chip) + '</strong>' : ''}
                </div>
                ${numeracionHtml}
            </div>
            ${btn}
        </div>`;
    }).join('');
}

async function entregar(id) {
    const numeroCorredorEl = document.getElementById(`numero_corredor-${id}`);
    const chipEl = document.getElementById(`chip-${id}`);
    const res = await fetch(`/pos/retiros/${id}/entregar`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
        body: JSON.stringify({
            entregado_por: entregadoPorInput.value || null,
            numero_corredor: numeroCorredorEl ? (numeroCorredorEl.value || null) : null,
            chip: chipEl ? (chipEl.value || null) : null,
        }),
    });
    const data = await res.json();
    if (data.success) {
        buscar();
    } else {
        // Cobro en sitio (12/08/2026): si el push-back de pago falla, el
        // backend no marca entregado — avisar al staff en vez de fallar
        // en silencio, para que no piense que ya se entregó.
        alert(data.error || 'No se pudo confirmar la entrega.');
    }
}

async function deshacer(id) {
    const res = await fetch(`/pos/retiros/${id}/deshacer`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
    });
    const data = await res.json();
    if (data.success) buscar();
}

function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

if (eventoSelect.value) buscar();
</script>
@endsection
