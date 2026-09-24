/**
 * Etiquetas de los eventos de `spatie/laravel-activitylog` que se muestran en
 * el panel de auditoría y en el resumen del panel administrativo.
 *
 * Un mismo evento tiene dos formas: una para insignia («Activó doble factor»)
 * y otra para incrustar en una oración («activó el doble factor»). Vive en un
 * solo archivo para que un evento de negocio nuevo se agregue en un solo
 * lugar en vez de en los dos componentes que lo consumen.
 */

export const eventLabels: Record<string, string> = {
    created: 'Creación',
    updated: 'Actualización',
    deleted: 'Eliminación',
    consultado: 'Consulta',
    doble_factor_activado: 'Activó doble factor',
    doble_factor_desactivado: 'Desactivó doble factor',
    teleconsulta_autorizada: 'Autorizó teleconsulta',
    autorizado: 'Autorizó tratamiento de datos',
    deactivated: 'Desactivó cuenta',
    reactivated: 'Reactivó cuenta',
};

export const eventVerbs: Record<string, string> = {
    created: 'creó',
    updated: 'actualizó',
    deleted: 'eliminó',
    consultado: 'consultó',
    doble_factor_activado: 'activó el doble factor',
    doble_factor_desactivado: 'desactivó el doble factor',
    teleconsulta_autorizada: 'autorizó la teleconsulta',
    autorizado: 'autorizó el tratamiento de sus datos',
    deactivated: 'desactivó la cuenta',
    reactivated: 'reactivó la cuenta',
};

/**
 * Humaniza un evento que no está en el mapa: mejor un texto razonable que la
 * clave cruda, para cuando alguien agregue un evento de negocio y olvide
 * registrar su etiqueta acá.
 */
export function humanizeEvent(event: string): string {
    const spaced = event.replace(/_/g, ' ');

    return spaced.charAt(0).toUpperCase() + spaced.slice(1);
}
