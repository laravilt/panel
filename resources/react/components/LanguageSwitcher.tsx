import { router, usePage } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import { useLocalization } from '@laravilt/support/composables/useLocalization';

interface Locale {
    value: string;
    label: string;
    dir: 'ltr' | 'rtl';
    flag: string;
}

interface PanelLocaleData {
    id: string;
    path: string;
    availableLocales: Locale[];
    currentLocale: string;
}

const getFlagUrl = (flag: string): string => {
    return `https://flagcdn.com/w20/${flag}.png`;
};

export default function LanguageSwitcher() {
    const { trans } = useLocalization();
    const page = usePage();
    const panel = (page.props as any)?.panel as PanelLocaleData | undefined;

    const availableLocales: Locale[] = panel?.availableLocales || [];
    const currentLocale = panel?.currentLocale || 'en';
    const panelPath = panel?.path || '';

    const currentLocaleData: Locale = availableLocales.find((l) => l.value === currentLocale) || {
        value: 'en',
        label: 'English',
        dir: 'ltr',
        flag: 'us',
    };

    const switchLocale = (locale: string) => {
        if (locale === currentLocale) return;

        // Build the URL using panel path
        const url = `/${panelPath}/locale`;

        router.post(
            url,
            {
                locale,
            },
            {
                preserveScroll: true,
                preserveState: false,
                onSuccess: () => {
                    // Use Inertia's reload instead of window.location.reload()
                    // This provides a smoother transition
                    router.reload({ only: [] });
                },
            },
        );
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon" className="relative">
                    <img
                        src={getFlagUrl(currentLocaleData.flag)}
                        alt={currentLocaleData.label}
                        className="h-5 w-5 rounded-sm object-cover"
                    />
                    <span className="sr-only">{trans('laravilt-panel::panel.language.switch')}</span>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-48">
                {availableLocales.map((locale) => (
                    <DropdownMenuItem
                        key={locale.value}
                        className="flex items-center gap-3 cursor-pointer"
                        onClick={() => switchLocale(locale.value)}
                    >
                        <img src={getFlagUrl(locale.flag)} alt={locale.label} className="h-4 w-4 rounded-sm object-cover" />
                        <span className={cn('text-start', { 'flex-1': currentLocaleData.dir === 'ltr' })}>
                            {locale.label}
                        </span>
                        {locale.value === currentLocale && <Check className="h-4 w-4 text-primary rtl:me-auto" />}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
