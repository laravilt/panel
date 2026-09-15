import type { ReactNode } from 'react';
// The Vue file imports `@laravilt/panel/layouts/app/AppSidebarLayout.vue`, which does not exist in this package
// (the app stubs ship `@/layouts/app/AppSidebarLayout.vue`). The React twin uses the app's sidebar layout.
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

interface AppLayoutProps {
    breadcrumbs?: BreadcrumbItemType[];
    children?: ReactNode;
}

export default function AppLayout({ breadcrumbs = [], children }: AppLayoutProps) {
    return <AppSidebarLayout breadcrumbs={breadcrumbs}>{children}</AppSidebarLayout>;
}
