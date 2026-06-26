import { useState } from 'react';

function formatearDisplay(valor) {
    const num = parseFloat(valor);
    if (isNaN(num)) return '';
    return new Intl.NumberFormat('es-CO').format(num);
}

export default function CampoMoneda({ label, name, value, onChange, error, ...props }) {
    const [display, setDisplay] = useState(value != null ? formatearDisplay(value) : '');

    const handleChange = (e) => {
        const raw = e.target.value.replace(/[^0-9]/g, '');
        setDisplay(raw ? new Intl.NumberFormat('es-CO').format(Number(raw)) : '');
        onChange(raw ? Number(raw) : '');
    };

    return (
        <div>
            {label && (
                <label className="block text-sm font-medium text-slate-700 mb-1">{label}</label>
            )}
            <div className="relative">
                <span className="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-400 select-none">$</span>
                <input
                    type="text"
                    inputMode="numeric"
                    value={display}
                    onChange={handleChange}
                    className={[
                        'w-full pl-7 pr-3 py-2 rounded-lg border text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition',
                        error ? 'border-red-400' : 'border-slate-300',
                    ].join(' ')}
                    {...props}
                />
            </div>
            {error && <p className="text-red-500 text-xs mt-1">{error}</p>}
        </div>
    );
}
