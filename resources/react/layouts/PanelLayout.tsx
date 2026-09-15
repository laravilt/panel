import { usePage } from '@inertiajs/react';
import { useEffect, type ReactNode } from 'react';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import AppSidebar from '@laravilt/panel/components/PanelSidebar';
import { NotificationContainer } from '@laravilt/notifications/app';

interface BreadcrumbItem {
    title: string;
    href: string;
}

interface PanelLayoutProps {
    breadcrumbs?: BreadcrumbItem[];
    children?: ReactNode;
}

// Panel Font Loading
const loadPanelFont = (panelData: any) => {
    const font = panelData?.font;
    if (font?.url && font?.family) {
        // Load font stylesheet
        const linkId = `panel-font-${font.family.replace(/\s+/g, '-').toLowerCase()}`;
        if (!document.getElementById(linkId)) {
            const link = document.createElement('link');
            link.id = linkId;
            link.rel = 'stylesheet';
            link.href = font.url;
            document.head.appendChild(link);
        }

        // Apply font family with !important to override Tailwind's @theme
        const fontValue = `"${font.family}", ui-sans-serif, system-ui, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol', 'Noto Color Emoji'`;
        document.documentElement.style.setProperty('--font-sans', fontValue);
        document.body.style.setProperty('font-family', fontValue, 'important');
        document.documentElement.style.setProperty('font-family', fontValue, 'important');
    }
};

export default function PanelLayout({ breadcrumbs = [], children }: PanelLayoutProps) {
    // Get shared panel data from Inertia
    const page = usePage();
    const pageProps = page.props as any;
    const rawPanel = pageProps?.panel;
    const panelData = (rawPanel ?? {}) as any;
    const user = pageProps?.auth?.user as any;

    // onMounted + watch(panelData)
    useEffect(() => {
        loadPanelFont(rawPanel ?? {});
    }, [rawPanel]);

    return (
        <AppShell variant="sidebar">
            <AppSidebar navigation={panelData?.navigation} panel={panelData} user={user} />
            <AppContent variant="sidebar" className="overflow-hidden">
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                {children}
            </AppContent>
            <NotificationContainer />
        </AppShell>
    );
}
