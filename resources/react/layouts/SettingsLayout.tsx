import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import PanelLayout from './PanelLayout';

interface NavigationItem {
    title: string;
    href: string;
    icon?: string;
    active?: boolean;
}

interface BreadcrumbItem {
    title: string;
    href: string;
}

interface SettingsLayoutProps {
    breadcrumbs?: BreadcrumbItem[];
    navigation?: NavigationItem[];
    title?: string;
    description?: string;
    loading?: boolean;
    children?: ReactNode;
}

export default function SettingsLayout({
    breadcrumbs,
    navigation,
    title,
    description,
    loading = false,
    children,
}: SettingsLayoutProps) {
    // Get current URL to determine active navigation item
    const currentUrl = typeof window !== 'undefined' ? window.location.pathname : '';

    // Enhance navigation items with active state
    const enhancedNavigation = navigation
        ? navigation.map((item) => ({
              ...item,
              active: currentUrl === item.href,
          }))
        : [];

    return (
        <PanelLayout breadcrumbs={breadcrumbs}>
            <div className="px-4 py-6">
                {/* Settings Title and Description */}
                {title && (
                    <div className="mb-8 space-y-0.5">
                        <h2 className="text-xl font-semibold tracking-tight">{title}</h2>
                        {description && <p className="text-sm text-muted-foreground">{description}</p>}
                    </div>
                )}

                <div className="flex flex-col lg:flex-row lg:space-x-12">
                    {/* Sidebar Navigation */}
                    <aside className="w-full max-w-xl lg:w-48">
                        <nav className="flex flex-col space-y-1 space-x-0">
                            {enhancedNavigation.map((item) => (
                                <Link
                                    key={item.href}
                                    href={item.href}
                                    data-slot="button"
                                    aria-current={item.active ? 'page' : undefined}
                                    className={cn(
                                        'inline-flex items-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all',
                                        'disabled:pointer-events-none disabled:opacity-50',
                                        "[&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0",
                                        'outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
                                        'aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive',
                                        'hover:bg-accent hover:text-accent-foreground dark:hover:bg-accent/50',
                                        'h-9 px-4 py-2 has-[>svg]:px-3 w-full justify-start',
                                        item.active ? 'bg-muted' : '',
                                    )}
                                >
                                    {item.title}
                                </Link>
                            ))}
                        </nav>
                    </aside>

                    {/* Separator for mobile */}
                    <div
                        data-orientation="horizontal"
                        role="none"
                        data-slot="separator-root"
                        className="bg-border shrink-0 data-[orientation=horizontal]:h-px data-[orientation=horizontal]:w-full data-[orientation=vertical]:h-full data-[orientation=vertical]:w-px my-6 lg:hidden"
                    ></div>

                    {/* Main Content Area */}
                    <div className="flex-1 md:max-w-2xl">
                        {loading ? (
                            // Loading Skeleton
                            <div className="max-w-2xl space-y-6">
                                {/* Page Header Skeleton */}
                                <header className="space-y-2">
                                    <Skeleton className="h-6 w-48" />
                                    <Skeleton className="h-4 w-80" />
                                </header>

                                {/* Content Skeleton */}
                                <div className="space-y-6">
                                    {/* Card/Section Skeleton */}
                                    <div className="flex items-start gap-3">
                                        <Skeleton className="h-10 w-10 rounded-lg shrink-0" />
                                        <div className="flex-1 space-y-2">
                                            <Skeleton className="h-5 w-32" />
                                            <Skeleton className="h-4 w-full max-w-md" />
                                        </div>
                                    </div>

                                    {/* Form Fields Skeleton */}
                                    <div className="space-y-4">
                                        <div className="space-y-2">
                                            <Skeleton className="h-4 w-24" />
                                            <Skeleton className="h-10 w-full" />
                                        </div>
                                        <div className="space-y-2">
                                            <Skeleton className="h-4 w-24" />
                                            <Skeleton className="h-10 w-full" />
                                        </div>
                                    </div>

                                    {/* Action Button Skeleton */}
                                    <Skeleton className="h-10 w-32" />
                                </div>
                            </div>
                        ) : (
                            // Actual Content
                            <div>{children}</div>
                        )}
                    </div>
                </div>
            </div>
        </PanelLayout>
    );
}
