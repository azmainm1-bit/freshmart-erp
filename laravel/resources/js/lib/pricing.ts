// Integer arithmetic for cart previews. The server recalculates every checkout.
export function scaled(value: string, places: number): bigint {
    if (!/^\d*(\.\d*)?$/.test(value)) return 0n;
    const [whole, fraction = ''] = value.split('.');
    return BigInt(whole || '0') * 10n ** BigInt(places) + BigInt((fraction + '0'.repeat(places)).slice(0, places));
}
function rounded(n: bigint, d: bigint): bigint { return (n + d / 2n) / d; }
export function decimal(value: bigint, places = 2): string {
    const sign = value < 0n ? '-' : ''; const v = value < 0n ? -value : value; const base = 10n ** BigInt(places);
    return `${sign}${v / base}.${String(v % base).padStart(places, '0')}`;
}
export function lineTotal(price: string, qty: string, rate: string, inclusive: boolean, discount = '0'): bigint {
    const gross = rounded(scaled(price, 2) * scaled(qty, 3), 1000n) - scaled(discount, 2);
    if (gross < 0n) return 0n;
    return inclusive ? gross : gross + rounded(gross * scaled(rate, 2), 10000n);
}
export function due(total: string, returned: string, paid: string, refunded = '0'): string {
    return decimal(scaled(total, 2) - scaled(returned, 2) - scaled(paid, 2) + scaled(refunded, 2));
}
