import { t } from '@/lib/i18n';

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

// "2023-07-01T00:00:00.000000Z" or "2023-07-01" -> "1 Jul 2023". Uses the date part only, so no timezone shift.
export function formatDate(value) {
    const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(value ?? ''));

    if (!m) {
        return value ? String(value) : '—';
    }

    return `${Number(m[3])} ${t(MONTHS[Number(m[2]) - 1])} ${m[1]}`;
}
