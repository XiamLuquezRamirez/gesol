// VITE_APP_BASE es el prefijo publico de la app ('/' en local, '/gesol/' en
// produccion). Prefija las imagenes de public/ (que NO estan en build/) para
// que carguen bien tanto en local como desplegado en una subcarpeta.
const base = import.meta.env.VITE_APP_BASE || '/';

export default function ApplicationLogo(props) {
    return (
        <img src={`${base}images/logo.png`} alt="Logo" width={500} />
    );
}
