import { usePage } from '@inertiajs/vue3';

/**
 * Translate an English UI string. The English text is the key; when there is no
 * translation (or the language is English) the text is returned unchanged.
 * {name} style placeholders are replaced from params.
 */
export function t(text, params = {}) {
    const dict = usePage().props.translations ?? {};
    let out = Object.prototype.hasOwnProperty.call(dict, text) ? dict[text] : text;
    for (const [k, v] of Object.entries(params)) out = out.replaceAll(`{${k}}`, String(v));
    return out;
}
