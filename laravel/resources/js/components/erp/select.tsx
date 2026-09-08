import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { cn } from '@/lib/utils';

export type SelectOption = { value: string; label: string; disabled?: boolean };

/** Themed drop-in replacement for a native `<select>` — the browser renders native option
 * lists outside the page's control, which breaks dark mode and the app's visual language. */
export function ErpSelect({
    value,
    onChange,
    options,
    placeholder = 'Select…',
    disabled,
    className,
}: {
    value: string;
    onChange: (value: string) => void;
    options: SelectOption[];
    placeholder?: string;
    disabled?: boolean;
    className?: string;
}) {
    return (
        <Select value={value || undefined} onValueChange={onChange} disabled={disabled}>
            <SelectTrigger className={cn('erp-input h-auto justify-between font-normal', className)}>
                <SelectValue placeholder={placeholder} />
            </SelectTrigger>
            <SelectContent>
                {options.map((o) => (
                    <SelectItem key={o.value} value={o.value} disabled={o.disabled}>
                        {o.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}
