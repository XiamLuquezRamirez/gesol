/**
 * Filtro de rango de fechas reutilizable para los reportes: Desde/Hasta + atajos
 * "Este mes" y "Mes anterior". Llama onConsultar(desde, hasta) al aplicar.
 */
export default function FiltroRango({ desde, hasta, setDesde, setHasta, onConsultar }) {
    const aplicar = (e) => {
        e?.preventDefault();
        onConsultar(desde, hasta);
    };

    const rango = (offset) => {
        const base = new Date();
        base.setDate(1);
        base.setMonth(base.getMonth() + offset);
        const inicio = new Date(base.getFullYear(), base.getMonth(), 1);
        const fin = new Date(base.getFullYear(), base.getMonth() + 1, 0);
        const fmt = (dt) => dt.toISOString().slice(0, 10);
        setDesde(fmt(inicio));
        setHasta(fmt(fin));
        onConsultar(fmt(inicio), fmt(fin));
    };

    return (
        <form onSubmit={aplicar} className="flex flex-wrap items-end gap-3 rounded-xl border border-slate-100 bg-white px-4 py-3 shadow-sm">
            <div>
                <label className="block text-xs font-medium text-slate-600 mb-1">Desde</label>
                <input type="date" value={desde} onChange={(e) => setDesde(e.target.value)}
                    className="rounded-lg border-slate-200 text-sm" />
            </div>
            <div>
                <label className="block text-xs font-medium text-slate-600 mb-1">Hasta</label>
                <input type="date" value={hasta} onChange={(e) => setHasta(e.target.value)}
                    className="rounded-lg border-slate-200 text-sm" />
            </div>
            <button type="submit"
                className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                Aplicar
            </button>
            <div className="flex gap-2">
                <button type="button" onClick={() => rango(0)}
                    className="rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-600 hover:bg-slate-50">
                    Este mes
                </button>
                <button type="button" onClick={() => rango(-1)}
                    className="rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-600 hover:bg-slate-50">
                    Mes anterior
                </button>
            </div>
        </form>
    );
}
