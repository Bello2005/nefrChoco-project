const dateTimeFormatter = new Intl.DateTimeFormat('es-CO', {
    day: '2-digit',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
});

const dateFormatter = new Intl.DateTimeFormat('es-CO', {
    day: '2-digit',
    month: 'long',
    year: 'numeric',
});

const shortDateFormatter = new Intl.DateTimeFormat('es-CO', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
});

export function formatDateTime(value: string | Date): string {
    return dateTimeFormatter.format(new Date(value)).replace('.', '');
}

export function formatDate(value: string | Date): string {
    return dateFormatter.format(new Date(value));
}

export function formatShortDate(value: string | Date): string {
    return shortDateFormatter.format(new Date(value));
}

/** Distancia relativa legible: "hace 5 min", "en 2 días". */
export function formatRelative(value: string | Date): string {
    const target = new Date(value).getTime();
    const diffSeconds = Math.round((target - Date.now()) / 1000);
    const absolute = Math.abs(diffSeconds);

    const units: [Intl.RelativeTimeFormatUnit, number][] = [
        ['year', 31536000],
        ['month', 2592000],
        ['day', 86400],
        ['hour', 3600],
        ['minute', 60],
    ];

    const formatter = new Intl.RelativeTimeFormat('es-CO', { numeric: 'auto' });

    for (const [unit, seconds] of units) {
        if (absolute >= seconds) {
            return formatter.format(Math.round(diffSeconds / seconds), unit);
        }
    }

    return 'hace un momento';
}

export function calculateAge(birthDate: string): number {
    const birth = new Date(birthDate);
    const now = new Date();
    let age = now.getFullYear() - birth.getFullYear();
    const monthDiff = now.getMonth() - birth.getMonth();

    if (monthDiff < 0 || (monthDiff === 0 && now.getDate() < birth.getDate())) {
        age--;
    }

    return age;
}

export function initialsFrom(name: string): string {
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('');
}
