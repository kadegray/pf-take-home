export function formatMicros(micros) {
    if (micros === null || micros === undefined) {
        return '—';
    }

    return '$' + (micros / 1_000_000).toFixed(4);
}
