import { Head } from '@inertiajs/react';
import ManageRecordsComponent from '@laravilt/panel/components/ManageRecords';
import PanelLayout from '@laravilt/panel/layouts/PanelLayout';

interface BreadcrumbItem {
    label: string;
    url: string | null;
}

interface ManageRecordsPageProps {
    page: {
        heading: string;
        subheading?: string | null;
        headerActions: any[];
        actionUrl?: string;
    };
    breadcrumbs?: BreadcrumbItem[];
    panelId?: string;
    resourceSlug: string;
    label: string;
    pluralLabel: string;
    icon?: string | null;
    canView: boolean;
    canCreate: boolean;
    canEdit: boolean;
    canDelete: boolean;
    form: any;
    infolist?: any;
    table: any;
    headerActions?: any[];
    queryRoute: string;
}

export default function ManageRecords(props: ManageRecordsPageProps) {
    // Use breadcrumbs from props (backend) or fallback to simple default
    const breadcrumbs: BreadcrumbItem[] =
        props.breadcrumbs && props.breadcrumbs.length > 0
            ? props.breadcrumbs
            : [
                  // Fallback: simple Dashboard → Current Page
                  {
                      label: 'Dashboard',
                      url: `/${props.panelId}`,
                  },
                  {
                      label: props.page.heading,
                      url: null,
                  },
              ];

    // Transform breadcrumbs to frontend format (label/url → title/href)
    const transformedBreadcrumbs = breadcrumbs.map((item) => ({
        title: item.label,
        href: item.url || '#',
    }));

    return (
        <>
            <Head title={props.page.heading} />

            <PanelLayout breadcrumbs={transformedBreadcrumbs}>
                <div className="flex flex-1 flex-col gap-4 p-4 min-h-0 overflow-hidden max-h-[calc(100vh-4rem)]">
                    {/* Page Content Area */}
                    <div className="flex-1 min-h-0 flex flex-col overflow-y-auto">
                        <ManageRecordsComponent
                            resourceSlug={props.resourceSlug}
                            panelId={props.panelId || ''}
                            label={props.label}
                            pluralLabel={props.pluralLabel}
                            icon={props.icon}
                            canView={props.canView}
                            canCreate={props.canCreate}
                            canEdit={props.canEdit}
                            canDelete={props.canDelete}
                            form={props.form}
                            infolist={props.infolist}
                            table={props.table}
                            headerActions={props.headerActions}
                            queryRoute={props.queryRoute}
                        />
                    </div>
                </div>
            </PanelLayout>
        </>
    );
}
