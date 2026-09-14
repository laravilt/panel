import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import PanelLayout from '@laravilt/panel/layouts/PanelLayout';
import WidgetRenderer from '@laravilt/widgets/components/WidgetRenderer';

interface BreadcrumbItem {
    label: string;
    url: string | null;
}

interface WidgetData {
    component: string;
    stats?: any[];
    columns?: number;
    [key: string]: any;
}

interface DashboardProps {
    title?: string;
    breadcrumbs?: BreadcrumbItem[];
    headerWidgets?: WidgetData[];
    footerWidgets?: WidgetData[];
    children?: ReactNode;
}

export default function Dashboard({ title, breadcrumbs, headerWidgets, footerWidgets, children }: DashboardProps) {
    // Transform breadcrumbs to frontend format
    const transformedBreadcrumbs = breadcrumbs
        ? breadcrumbs.map((item) => ({
              title: item.label,
              href: item.url || '#',
          }))
        : [];

    return (
        <>
            <Head title={title || 'Dashboard'} />

            <PanelLayout breadcrumbs={transformedBreadcrumbs}>
                <div className="flex flex-1 flex-col gap-6 p-4">
                    {/* Header Widgets */}
                    {headerWidgets && headerWidgets.length > 0 && <WidgetRenderer widgets={headerWidgets} />}

                    {/* Main Content Area (for extending) */}
                    {children}

                    {/* Footer Widgets */}
                    {footerWidgets && footerWidgets.length > 0 && <WidgetRenderer widgets={footerWidgets} />}
                </div>
            </PanelLayout>
        </>
    );
}
