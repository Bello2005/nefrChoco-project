import { describe, expect, it } from 'vitest';
import { navByRole } from './navigation';

function urlsOf(role: string): string[] {
    return navByRole[role].flatMap((section) => section.items.map((item) => item.url));
}

describe('menú por rol', () => {
    it('médicos y pacientes encuentran el cuestionario de usabilidad en su menú', () => {
        expect(urlsOf('medico')).toContain('/usabilidad');
        expect(urlsOf('paciente')).toContain('/usabilidad');
    });

    it('el reporte de usabilidad solo aparece en el menú del admin', () => {
        expect(urlsOf('admin')).toContain('/admin/usabilidad');
        expect(urlsOf('medico')).not.toContain('/admin/usabilidad');
        expect(urlsOf('paciente')).not.toContain('/admin/usabilidad');
    });
});
