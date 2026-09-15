import type { ReactNode } from 'react';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import PanelSidebar from './PanelSidebar';

interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface PanelPageLayoutProps {
    breadcrumbs?: BreadcrumbItem[];
    heading?: string;
    subheading?: string | null;
    headerActions?: any[];
    content?: string | null;
    navigation?: any[];
    panel?: {
        id: string;
        path: string;
        brandName: string;
        brandLogo?: string;
    };
    user?: {
        name: string;
        email: string;
    };
    /** Vue default slot (Blade usage): when provided it is rendered as-is. */
    children?: ReactNode;
    /**
     * Vue named slot `content`. Renamed because the component already has a `content` (HTML string) prop.
     */
    contentSlot?: ReactNode;
}

export default function PanelPageLayout({
    breadcrumbs,
    heading,
    subheading,
    headerActions,
    content,
    navigation,
    panel,
    user,
    children,
    contentSlot,
}: PanelPageLayoutProps) {
    // Slot mode for Blade usage
    if (children != null) {
        return <>{children}</>;
    }

    // Direct mode
    return (
        <AppShell variant="sidebar">
            <PanelSidebar navigation={navigation} panel={panel} user={user} />
            <AppContent variant="sidebar" className="overflow-x-hidden">
                <AppSidebarHeader breadcrumbs={breadcrumbs} />

                <div className="flex h-full flex-1 flex-col gap-4 p-4">
                    <div className="flex items-center justify-between">
                        <div>
                            <h1 className="text-2xl font-semibold">{heading}</h1>
                            {subheading && <p className="text-sm text-muted-foreground mt-1">{subheading}</p>}
                        </div>

                        {/* Header actions placeholder */}
                        {headerActions && headerActions.length > 0 && (
                            <div>{/* TODO: Render header actions when actions package is ready */}</div>
                        )}
                    </div>

                    {/* Page Content Area */}
                    <div className="flex-1">
                        {content ? (
                            <div dangerouslySetInnerHTML={{ __html: content }} />
                        ) : (
                            (contentSlot ?? (
                                <div className="flex h-full items-center justify-center rounded-lg border border-dashed p-8">
                                    <div className="text-center">
                                        <h3 className="text-lg font-semibold">Custom Page Content</h3>
                                        <p className="text-sm text-muted-foreground mt-2">
                                            This is a standalone page. You can add custom components and content here.
                                        </p>
                                    </div>
                                </div>
                            ))
                        )}
                    </div>
                </div>
            </AppContent>
        </AppShell>
    );
}
