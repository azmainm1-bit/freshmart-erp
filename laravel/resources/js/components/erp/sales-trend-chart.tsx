import { money } from '@/lib/format';
import { Area, AreaChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

type TrendPoint = { day: string; net_sales: string; profit: string };

function shortDate(value: string) {
    return new Intl.DateTimeFormat('en-GB', { timeZone: 'Asia/Dhaka', day: 'numeric', month: 'short' }).format(new Date(`${value}T12:00:00Z`));
}

function ChartTooltip({ active, payload, label }: { active?: boolean; payload?: { value: number; dataKey: string }[]; label?: string }) {
    if (!active || !payload?.length) return null;
    const netSales = payload.find((p) => p.dataKey === 'net_sales')?.value ?? 0;
    const profit = payload.find((p) => p.dataKey === 'profit')?.value ?? 0;
    return (
        <div className="bg-popover text-popover-foreground rounded-lg border px-3 py-2 text-xs shadow-md">
            <p className="mb-1.5 font-medium">{shortDate(label ?? '')}</p>
            <p className="flex items-center gap-1.5">
                <span className="inline-block size-2 rounded-full bg-emerald-500" />
                Net sales <span className="ml-auto font-medium tabular-nums">{money(netSales)}</span>
            </p>
            <p className="flex items-center gap-1.5">
                <span className="inline-block size-2 rounded-full bg-blue-500" />
                Gross profit <span className="ml-auto font-medium tabular-nums">{money(profit)}</span>
            </p>
        </div>
    );
}

export function SalesTrendChart({ trend }: { trend: TrendPoint[] }) {
    if (!trend.length) return <p className="text-muted-foreground p-6 text-sm">Sales trends appear after the first posted sale.</p>;
    const data = trend.map((d) => ({ day: d.day, net_sales: Number(d.net_sales), profit: Number(d.profit) }));
    return (
        <div role="region" aria-label="Net sales and gross profit over the last 30 days">
            <div className="text-muted-foreground flex flex-wrap gap-5 px-5 pt-4 text-xs">
                <span className="flex items-center gap-2">
                    <span className="h-0.5 w-4 bg-emerald-500" />
                    Net sales
                </span>
                <span className="flex items-center gap-2">
                    <span className="h-0.5 w-4 bg-blue-500" />
                    Gross profit
                </span>
            </div>
            <div className="h-64 px-2 pt-2 pb-2 sm:px-4">
                <ResponsiveContainer width="100%" height="100%">
                    <AreaChart data={data} margin={{ top: 8, right: 8, left: 8, bottom: 0 }}>
                        <defs>
                            <linearGradient id="netSalesFill" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stopColor="var(--chart-1)" stopOpacity={0.35} />
                                <stop offset="100%" stopColor="var(--chart-1)" stopOpacity={0.02} />
                            </linearGradient>
                        </defs>
                        <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="var(--border)" />
                        <XAxis
                            dataKey="day"
                            tickFormatter={shortDate}
                            tick={{ fontSize: 12, fill: 'var(--muted-foreground)' }}
                            axisLine={{ stroke: 'var(--border)' }}
                            tickLine={false}
                            minTickGap={24}
                        />
                        <YAxis
                            tickFormatter={(v: number) => (Math.abs(v) >= 1000 ? `${(v / 1000).toFixed(0)}k` : String(v))}
                            tick={{ fontSize: 12, fill: 'var(--muted-foreground)' }}
                            axisLine={false}
                            tickLine={false}
                            width={40}
                        />
                        <Tooltip content={<ChartTooltip />} cursor={{ stroke: 'var(--border)' }} />
                        <Area
                            isAnimationActive={false}
                            type="monotone"
                            dataKey="net_sales"
                            stroke="var(--chart-1)"
                            strokeWidth={2}
                            fill="url(#netSalesFill)"
                            name="Net sales"
                        />
                        <Area
                            isAnimationActive={false}
                            type="monotone"
                            dataKey="profit"
                            stroke="var(--chart-2)"
                            strokeWidth={2}
                            fill="none"
                            name="Gross profit"
                        />
                    </AreaChart>
                </ResponsiveContainer>
            </div>
        </div>
    );
}
