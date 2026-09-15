import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import PanelLayout from '@laravilt/panel/layouts/PanelLayout';

interface LaraviltPageProps {
    page?: {
        heading?: string;
        subheading?: string;
        title?: string;
    };
    breadcrumbs?: Array<{ title: string; href?: string }>;
    panel?: {
        id: string;
        path: string;
        brandName: string;
        brandLogo?: string;
    };
    children?: ReactNode;
}

export default function LaraviltPage({ page, breadcrumbs = [], children }: LaraviltPageProps) {
    return (
        <>
            <Head title={page?.title || page?.heading || 'Page'} />

            <PanelLayout breadcrumbs={breadcrumbs as Array<{ title: string; href: string }>}>
                <div className="flex h-full flex-1 flex-col gap-4 p-4">
                    {page?.heading && (
                        <div className="flex items-center justify-between">
                            <div>
                                <h1 className="text-2xl font-semibold">{page.heading}</h1>
                                {page.subheading && <p className="text-sm text-muted-foreground mt-1">{page.subheading}</p>}
                            </div>
                        </div>
                    )}

                    {/* Page Content Slot */}
                    <div className="flex-1">{children}</div>
                </div>
            </PanelLayout>
        </>
    );
}
