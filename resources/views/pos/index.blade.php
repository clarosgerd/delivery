@extends('layouts.app')

@section('title', 'POS — Retiro en sitio')

@section('content')
<div class="max-w-2xl mx-auto">
    <x-card>
        <div class="flex justify-between items-center mb-1">
            <label class="block text-sm font-medium">Evento</label>
            <a href="{{ route('pos.index') }}" class="text-xs text-brand-600 hover:underline">‹ Cambiar evento</a>
        </div>
        <div class="text-lg font-semibold">{{ $evento->evento_id }} — {{ $evento->evento_nombre ?? 'sin nombre' }}</div>
        <label class="block text-sm font-medium mt-3 mb-1">Nombre de quien entrega (queda en el registro, opcional)</label>
        <input type="text" id="entregado_por" class="w-full border border-slate-300 rounded-md px-3 py-2">
    </x-card>

    <x-card>
        <input type="text" id="q" placeholder="Buscar por documento, apellido o referencia..." autocomplete="off"
            class="w-full text-xl px-4 py-4 rounded-lg border-2 border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-600 focus:border-brand-600">
        <div id="resultados" class="mt-3 space-y-3"></div>
    </x-card>
</div>

{{-- Editar datos del participante al momento de la entrega (28/09/2026) —
     nombre/apellido/género/fecha de nacimiento libres; categoría solo si
     ApiRestEvent confirma que el precio no cambia (si no, el mensaje de
     "debe pasar por Caja" se muestra acá mismo, sin cerrar el popup). --}}
<dialog id="editarDatosModal" class="rounded-xl p-0 w-full max-w-md backdrop:bg-black/40">
    <form method="dialog" class="p-5" onsubmit="return false;">
        <h2 class="text-lg font-semibold mb-3">Editar datos</h2>
        <div class="space-y-3">
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Nombre</label>
                <input type="text" id="ed_nombre" class="w-full border border-slate-300 rounded-md px-3 py-2">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Apellido</label>
                <input type="text" id="ed_apellido" class="w-full border border-slate-300 rounded-md px-3 py-2">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Sexo</label>
                <select id="ed_genero" class="w-full border border-slate-300 rounded-md px-3 py-2">
                    <option value="Masculino">Masculino</option>
                    <option value="Femenino">Femenino</option>
                    <option value="Otro">Otro</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Fecha de nacimiento</label>
                <input type="date" id="ed_fecha_nacimiento" class="w-full border border-slate-300 rounded-md px-3 py-2">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Categoría</label>
                <select id="ed_categoria" class="w-full border border-slate-300 rounded-md px-3 py-2"></select>
                <p id="ed_categoria_nota" class="text-xs text-slate-500 mt-1"></p>
            </div>
        </div>
        <p id="ed_error" class="text-sm text-red-700 bg-red-50 border border-red-200 rounded-md px-3 py-2 mt-3" hidden></p>
        <div class="flex justify-end gap-2 mt-4">
            <button type="button" onclick="cerrarEditarDatos()" class="px-4 py-2 rounded-md bg-slate-100 hover:bg-slate-200 text-sm">Cancelar</button>
            <button type="button" id="ed_guardar" onclick="guardarEditarDatos()" class="px-4 py-2 rounded-md bg-brand-600 hover:bg-brand-700 text-white text-sm">Guardar</button>
        </div>
    </form>
</dialog>

<script>
const eventoId = {{ $evento->evento_id }};
// Bug real (23/09/2026) — las URLs de entregar/deshacer estaban
// hardcodeadas como ruta absoluta (`/pos/${eventoId}/...`), que el
// navegador resuelve contra la RAÍZ del dominio. Como esta app vive en una
// subcarpeta (ej. www.inscrito.net/delivery/public/...), esa ruta
// terminaba pegándole a www.inscrito.net/pos/... directo (sin el prefijo
// de la subcarpeta) — 404 real. `route()` ya arma la URL completa
// correcta (mismo patrón que ya usaba `buscar()` más abajo, que nunca
// tuvo este problema) — se arma acá con un placeholder para el id de
// retiro, que se reemplaza en JS.
const entregarUrlTemplate = @json(route('pos.entregar', [$evento, '__RETIRO__']));
const deshacerUrlTemplate = @json(route('pos.deshacer', [$evento, '__RETIRO__']));
// Numeración/chip solo aplica a carreras, no a congresos (16/09/2026) —
// ver OrganizadorDashboardController::exportCsv (ApiRestEvent) y
// RetiroSyncService::sincronizar(). Default true en la BD, así que un
// evento nunca sincronizado con este cambio sigue mostrando numeración
// como siempre.
const usaNumeracion = @json($evento->usa_numeracion);
// Editar datos del participante al momento de la entrega (28/09/2026) —
// catálogo de categorías del evento agrupado por nombre de tipo de
// formulario (ver OrganizadorDashboardController::exportCsv en ApiRestEvent
// y RetiroSyncService). Puede ser null si el evento todavía no sincronizó
// con esta versión — el select de categoría se deshabilita en ese caso.
const catalogoCategorias = @json($evento->categorias_catalogo ?? null);
const editarDatosUrlTemplate = @json(route('pos.editar-datos', [$evento, '__RETIRO__']));
const qInput = document.getElementById('q');
const entregadoPorInput = document.getElementById('entregado_por');
const resultadosEl = document.getElementById('resultados');
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

entregadoPorInput.value = localStorage.getItem('pos_entregado_por') || '';
entregadoPorInput.addEventListener('input', () => localStorage.setItem('pos_entregado_por', entregadoPorInput.value));

let timer = null;
qInput.addEventListener('input', () => {
    clearTimeout(timer);
    timer = setTimeout(buscar, 300);
});

async function buscar() {
    const q = qInput.value.trim();
    if (q.length < 2) {
        resultadosEl.innerHTML = '';
        return;
    }
    const url = `{{ route('pos.buscar', $evento) }}?q=${encodeURIComponent(q)}`;
    const res = await fetch(url);
    const items = await res.json();
    render(items);
}

// Aviso de numeración vs. género/edad real en entrega de kit (16/09/2026)
// — registro de la última respuesta conocida por id, para que entregar()
// pueda recuperar el N° corredor/chip vigente de una tarjeta de solo
// lectura (sin <input> en el DOM) y reenviarlo sin cambios en vez de
// mandar null (que lo borraría, ver RetiroSitio::asignarNumeracion()).
let itemsPorId = {};

function render(items) {
    itemsPorId = {};
    items.forEach(r => { itemsPorId[r.id] = r; });

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
        // Aviso de numeración vs. género/edad real en entrega de kit
        // (16/09/2026) — mismo patrón visual ámbar que "pago pendiente",
        // pero es puramente informativo: no condiciona el botón de abajo.
        // Numeración/chip solo aplica a carreras, no a congresos
        // (16/09/2026) — sin esto, un congreso (ej. COLABIOCLI) mostraba
        // igual el aviso ámbar/sección de N° corredor y chip, que no
        // tienen ningún sentido ahí.
        const tieneAlertaNumeracion = usaNumeracion && !entregado && !!r.alerta_numeracion;
        const cardClass = entregado
            ? 'bg-green-50 border-green-200'
            : (pendienteDeCobro || tieneAlertaNumeracion) ? 'bg-amber-50 border-amber-300' : 'bg-white border-slate-200';
        const btn = entregado
            ? `<button onclick="deshacer(${r.id})" class="text-lg px-6 py-3.5 rounded-lg whitespace-nowrap bg-slate-200 hover:bg-slate-300 text-slate-800">Deshacer</button>`
            : pendienteDeCobro
                ? `<button onclick="entregar(${r.id})" class="text-lg px-6 py-3.5 rounded-lg whitespace-nowrap bg-amber-600 hover:bg-amber-700 text-white">Cobrar Bs ${escapeHtml(r.monto ?? '?')} y confirmar entrega</button>`
                : `<button onclick="entregar(${r.id})" class="text-lg px-6 py-3.5 rounded-lg whitespace-nowrap bg-brand-600 hover:bg-brand-700 text-white">Confirmar entrega</button>`;
        // Editar datos (28/09/2026) — disponible en pendiente y en entregado
        // (corregir un dato después de la entrega es un caso real).
        const editarBtn = `<button onclick="abrirEditarDatos(${r.id})" class="text-sm px-4 py-2 rounded-lg whitespace-nowrap bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 block mt-2">Editar datos</button>`;

        // Numeración: si ya vino cargada (el proveedor llegó a tiempo para
        // esta persona), se muestra de solo lectura — el proveedor no
        // llegó a tiempo es la excepción, no el default. Si falta, se
        // ofrecen inputs para cargarla a mano al momento de entregar.
        // Aviso de numeración vs. género/edad real en entrega de kit
        // (16/09/2026) — género/fecha de nacimiento no se pintaban pese a
        // venir en la respuesta desde ahora. La edad se muestra con
        // `edad_calculada` (la que ApiRestEvent usó de verdad para decidir
        // si avisar, según el método de la categoría — ver
        // CalculoEdadResolver), NO con la fecha de hoy: mostrar la edad
        // "de hoy" confundía al staff en categorías con "edad que cumple
        // en el año del evento", donde puede diferir de la edad calendario
        // actual. calcularEdad() queda como respaldo solo si por algún
        // motivo edad_calculada no llegó (sync viejo, categoría sin
        // resolver). `alerta_numeracion` es un aviso, nunca bloquea
        // "Confirmar entrega" ni toca la inscripción.
        const edad = (r.edad_calculada !== null && r.edad_calculada !== undefined && r.edad_calculada !== '')
            ? r.edad_calculada
            : (r.fecha_nacimiento ? calcularEdad(r.fecha_nacimiento) : null);
        const generoEdadHtml = (r.genero || edad !== null)
            ? `<br>${escapeHtml(r.genero || '—')}${edad !== null ? ', ' + edad + ' años' : ''}`
            : '';
        const alertaNumeracionHtml = tieneAlertaNumeracion
            ? '<br><strong class="text-amber-700">⚠ Numeración: ' + escapeHtml(r.alerta_numeracion) + '</strong>'
            : '';
        // Fusión de inscripciones duplicadas por persona — curso
        // pre-congreso (16/09/2026) — solo aparece si esta persona además
        // de su inscripción principal (la que se ve en "Categoría") tiene
        // una a un curso pre-congreso — ver
        // OrganizadorDashboardController::exportCsv, que fusiona ambas
        // filas del CSV en una sola para no perder ninguna de las dos acá.
        const cursoHtml = r.nombre_curso
            ? '<br>Curso: <strong>' + escapeHtml(r.nombre_curso) + '</strong>'
            : '';

        // Recategorización visual por edad/género (23/09/2026) — solo
        // aparece si ApiRestEvent calculó una categoría real distinta a la
        // que el participante eligió (ver categoria más arriba). Nunca
        // cambia la inscripción, es puramente informativo para el staff —
        // por eso no usa el mismo ámbar que alertaNumeracionHtml (eso es
        // para avisos que requieren corregir algo).
        const categoriaRecalculadaHtml = (r.categoria_recalculada && r.categoria_recalculada !== r.categoria)
            ? ' · Real por edad/género: <span style="display:inline-block;width:10px;height:10px;border-radius:50%;'
                + 'background:' + escapeHtml(r.categoria_recalculada_color || '#94a3b8') + ';vertical-align:middle;margin-right:3px;"></span>'
                + '<strong>' + escapeHtml(r.categoria_recalculada) + '</strong>'
            : '';

        const tieneNumeracion = r.numero_corredor && r.chip;
        // Aviso de numeración vs. género/edad real en entrega de kit
        // (16/09/2026) — antes se mostraba de solo lectura apenas ya
        // tenía número/chip cargados del proveedor; ahora siempre se
        // muestra editable mientras no esté entregado (pedido del
        // usuario: el staff puede notar y corregir un error aunque el
        // chequeo automático de género/edad no lo haya marcado). Ver
        // RetiroSitio::asignarNumeracion(), que acepta pisar un valor
        // existente cuando el que se manda es distinto — si nadie edita
        // nada, se reenvía el mismo valor y queda como no-op.
        // Numeración/chip solo aplica a carreras, no a congresos — ver
        // comentario de `tieneAlertaNumeracion` arriba.
        const numeracionHtml = (entregado || !usaNumeracion) ? '' : `<div class="mt-2 flex gap-2">
                <input type="text" id="numero_corredor-${r.id}" placeholder="N° corredor" value="${escapeHtml(r.numero_corredor || '')}"
                    class="w-28 border border-slate-300 rounded-md px-2 py-1.5 text-sm">
                <input type="text" id="chip-${r.id}" placeholder="Chip" value="${escapeHtml(r.chip || '')}"
                    class="w-28 border border-slate-300 rounded-md px-2 py-1.5 text-sm">
               </div>${tieneAlertaNumeracion ? '<div class="text-xs text-amber-700 mt-1">Corregí acá si el número/chip está mal.</div>' : ''}`;

        return `
        <div id="retiro-${r.id}" class="border rounded-xl px-5 py-4 flex justify-between items-center gap-3 ${cardClass}">
            <div>
                <div class="text-xl font-bold">${escapeHtml(r.nombre || '')} ${escapeHtml(r.apellido || '')}</div>
                <div class="text-sm text-slate-600 mt-1">
                    Doc: ${escapeHtml(r.documento || '—')} · Categoría: ${escapeHtml(r.categoria || '—')}${categoriaRecalculadaHtml} ·
                    Talla: ${escapeHtml(r.talla || '—')} ${r.souvenirs ? '· ' + escapeHtml(r.souvenirs) : ''}
                    <br>Ref: ${escapeHtml(r.referencia || '—')}
                    ${cursoHtml}
                    ${generoEdadHtml}
                    ${pendienteDeCobro ? '<br><strong class="text-amber-700">Pendiente de pago — Bs ' + escapeHtml(r.monto ?? '?') + '</strong>' : ''}
                    ${alertaNumeracionHtml}
                    ${entregado ? '<br><strong>Entregado' + (r.entregado_por ? ' por ' + escapeHtml(r.entregado_por) : '') + '</strong>' : ''}
                    ${entregado && usaNumeracion && tieneNumeracion ? '<br>N° corredor: <strong>' + escapeHtml(r.numero_corredor) + '</strong> · Chip: <strong>' + escapeHtml(r.chip) + '</strong>' : ''}
                </div>
                ${numeracionHtml}
            </div>
            <div class="flex flex-col items-end">
                ${btn}
                ${editarBtn}
            </div>
        </div>`;
    }).join('');
}

async function entregar(id) {
    const numeroCorredorEl = document.getElementById(`numero_corredor-${id}`);
    const chipEl = document.getElementById(`chip-${id}`);
    // Aviso de numeración vs. género/edad real en entrega de kit
    // (16/09/2026) — una tarjeta de solo lectura no tiene <input>, así que
    // sin este fallback se mandaría null y borraría un N° corredor/chip
    // que ya estaba bien (ver RetiroSitio::asignarNumeracion()). Se manda
    // el valor vigente sin cambios en vez de null.
    const item = itemsPorId[id] || {};
    const numeroCorredor = numeroCorredorEl ? (numeroCorredorEl.value || null) : (item.numero_corredor || null);
    const chip = chipEl ? (chipEl.value || null) : (item.chip || null);
    const res = await fetch(entregarUrlTemplate.replace('__RETIRO__', id), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
        body: JSON.stringify({
            entregado_por: entregadoPorInput.value || null,
            numero_corredor: numeroCorredor,
            chip: chip,
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
    const res = await fetch(deshacerUrlTemplate.replace('__RETIRO__', id), {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
    });
    const data = await res.json();
    if (data.success) buscar();
}

// Editar datos del participante al momento de la entrega (28/09/2026) ──────
let editarDatosRetiroId = null;
const editarDatosModal = document.getElementById('editarDatosModal');

function abrirEditarDatos(id) {
    const item = itemsPorId[id];
    if (!item) return;
    editarDatosRetiroId = id;

    document.getElementById('ed_nombre').value = item.nombre || '';
    document.getElementById('ed_apellido').value = item.apellido || '';
    document.getElementById('ed_genero').value = item.genero || 'Masculino';
    document.getElementById('ed_fecha_nacimiento').value = (item.fecha_nacimiento || '').slice(0, 10);

    const categoriaSelect = document.getElementById('ed_categoria');
    const categoriaNota = document.getElementById('ed_categoria_nota');
    const opciones = catalogoCategorias && item.tipo_formulario ? catalogoCategorias[item.tipo_formulario] : null;
    if (opciones && opciones.length) {
        categoriaSelect.innerHTML = opciones.map(o =>
            `<option value="${o.id}" ${String(o.id) === String(item.categoria_id) ? 'selected' : ''}>${escapeHtml(o.name)}</option>`
        ).join('');
        categoriaSelect.disabled = false;
        categoriaNota.textContent = 'Si el precio de la nueva categoría es distinto, no se aplicará ningún cambio — pasá por Caja.';
    } else {
        // Sin catálogo (falta un sync) o sin categorías para este tipo de
        // formulario — se deja ver la actual, sin poder cambiarla.
        categoriaSelect.innerHTML = item.categoria ? `<option value="${item.categoria_id || ''}">${escapeHtml(item.categoria)}</option>` : '<option value="">—</option>';
        categoriaSelect.disabled = true;
        categoriaNota.textContent = 'No se puede cambiar la categoría todavía (falta sincronizar).';
    }

    document.getElementById('ed_error').hidden = true;
    document.getElementById('ed_guardar').disabled = false;
    editarDatosModal.showModal();
}

function cerrarEditarDatos() {
    editarDatosModal.close();
    editarDatosRetiroId = null;
}

async function guardarEditarDatos() {
    if (!editarDatosRetiroId) return;
    const id = editarDatosRetiroId;
    const item = itemsPorId[id] || {};
    const errorEl = document.getElementById('ed_error');
    const guardarBtn = document.getElementById('ed_guardar');
    errorEl.hidden = true;

    const nombre = document.getElementById('ed_nombre').value.trim();
    const apellido = document.getElementById('ed_apellido').value.trim();
    const genero = document.getElementById('ed_genero').value;
    const fechaNacimiento = document.getElementById('ed_fecha_nacimiento').value;
    const categoriaSelect = document.getElementById('ed_categoria');
    const categoriaId = categoriaSelect.disabled ? null : categoriaSelect.value;

    // Solo se manda lo que realmente cambió.
    const cambios = {};
    if (nombre && nombre !== (item.nombre || '')) cambios.nombre = nombre;
    if (apellido && apellido !== (item.apellido || '')) cambios.apellido = apellido;
    if (genero && genero !== (item.genero || '')) cambios.genero = genero;
    if (fechaNacimiento && fechaNacimiento !== (item.fecha_nacimiento || '').slice(0, 10)) cambios.fecha_nacimiento = fechaNacimiento;
    if (categoriaId && String(categoriaId) !== String(item.categoria_id || '')) cambios.categoria_id = categoriaId;

    if (!Object.keys(cambios).length) {
        cerrarEditarDatos();
        return;
    }

    guardarBtn.disabled = true;
    try {
        const res = await fetch(editarDatosUrlTemplate.replace('__RETIRO__', id), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: JSON.stringify(cambios),
        });
        const data = await res.json();
        if (data.success) {
            cerrarEditarDatos();
            buscar();
        } else {
            errorEl.textContent = data.error || 'No se pudo guardar el cambio.';
            errorEl.hidden = false;
        }
    } catch (e) {
        errorEl.textContent = 'No se pudo conectar. Reintentá.';
        errorEl.hidden = false;
    } finally {
        guardarBtn.disabled = false;
    }
}

function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

// Solo para mostrar junto al género en la tarjeta — la edad usada para
// resolver `alerta_numeracion` ya viene calculada del lado de ApiRestEvent
// (ver CalculoEdadResolver), esto no participa de esa validación.
function calcularEdad(fechaNacimiento) {
    const nacimiento = new Date(fechaNacimiento);
    if (isNaN(nacimiento.getTime())) return null;
    const hoy = new Date();
    let edad = hoy.getFullYear() - nacimiento.getFullYear();
    const noCumplioAun = (hoy.getMonth() < nacimiento.getMonth())
        || (hoy.getMonth() === nacimiento.getMonth() && hoy.getDate() < nacimiento.getDate());
    if (noCumplioAun) edad--;
    return edad;
}

</script>
@endsection
