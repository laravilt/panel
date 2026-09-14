import { Inbox } from 'lucide-react';
import { useEffect, useState } from 'react';
import ActionButton from '@laravilt/actions/components/ActionButton';
import { useLocalization } from '@laravilt/support/composables';
import { resolveIcon } from '@laravilt/support/lib/icons';
import Table from '@laravilt/tables/components/Table';

export interface RelationManagerProps {
    relationship: string;
    label: string;
    pluralLabel: string;
    icon?: string | null;
    recordTitleAttribute?: string | null;
    readOnly: boolean;
    canCreate: boolean;
    canEdit: boolean;
    canDelete: boolean;
    form: any;
    infolist?: any;
    table: any;
    headerActions?: any[];
    ownerRecordId: string | number;
    resourceSlug: string;
    panelId: string;
}

const skeletonRows = Array.from({ length: 5 }, (_, index) => index + 1);

export default function RelationManager({
    relationship,
    label,
    pluralLabel,
    icon,
    canEdit,
    canDelete,
    form,
    infolist,
    table,
    headerActions,
    ownerRecordId,
    resourceSlug,
    panelId,
}: RelationManagerProps) {
    const { trans } = useLocalization();

    // State for records
    const [records, setRecords] = useState<any[]>([]);
    const [isLoading, setIsLoading] = useState(true);
    const [pagination, setPagination] = useState<any>({
        total: 0,
        per_page: 10,
        current_page: 1,
        last_page: 1,
        from: 0,
        to: 0,
    });

    // Build the query route for the relation
    const queryRoute = `/${panelId}/${resourceSlug}/${ownerRecordId}/relations/${relationship}`;

    // Configure header actions with the correct URL for submission
    const configuredHeaderActions: any[] = headerActions
        ? headerActions.map((action) => ({
              ...action,
              url: queryRoute, // Set URL for the action to submit to
              useAjax: true, // Use fetch instead of Inertia to avoid page reload
          }))
        : [];

    // Configure record actions with proper URLs for view/edit/delete
    const configuredRecordActions: any[] = [];

    // Get record actions from table config
    const tableRecordActions = table?.recordActions || [];

    // Process each action and configure URLs
    tableRecordActions.forEach((action: any) => {
        if (action.name === 'view') {
            configuredRecordActions.push({
                ...action,
                // URL will be set per-record in the Table component
                useAjax: true,
                requiresConfirmation: true,
                modalHeading: trans('actions::actions.buttons.view') + ' ' + label,
                // Use infolist schema if available, otherwise fall back to form schema
                modalInfolistSchema: infolist?.schema || null,
                modalFormSchema: infolist?.schema ? null : form?.schema || [],
                modalSubmitActionLabel: null, // No submit button for view
                modalCancelActionLabel: trans('actions::actions.buttons.close'),
                isViewOnly: true, // Flag to indicate view-only mode
                modalWidth: 'lg', // Larger modal for view
            });
        } else if (action.name === 'edit' && canEdit) {
            configuredRecordActions.push({
                ...action,
                // URL will be set per-record in the Table component
                useAjax: true,
                method: 'PUT',
                requiresConfirmation: true,
                modalHeading: trans('actions::actions.buttons.edit') + ' ' + label,
                modalFormSchema: form?.schema || [],
                modalSubmitActionLabel: trans('actions::actions.buttons.save'),
                modalCancelActionLabel: trans('actions::actions.buttons.cancel'),
                modalWidth: 'lg', // Larger modal for edit
            });
        } else if (action.name === 'delete' && canDelete) {
            configuredRecordActions.push({
                ...action,
                useAjax: true,
                method: 'DELETE',
                requiresConfirmation: true,
                modalHeading: trans('actions::actions.buttons.delete') + ' ' + label,
                modalDescription: trans('actions::actions.confirm_delete_description'),
                modalSubmitActionLabel: trans('actions::actions.buttons.delete'),
                modalCancelActionLabel: trans('actions::actions.buttons.cancel'),
            });
        } else {
            // Pass through other actions as-is
            configuredRecordActions.push({
                ...action,
                useAjax: true,
            });
        }
    });

    // Configure bulk actions with proper URL
    const configuredBulkActions: any[] = [];

    // Get bulk actions from table config
    const tableBulkActions = table?.bulkActions || [];

    tableBulkActions.forEach((action: any) => {
        if (action.name === 'delete' || action.name === 'bulk-delete') {
            configuredBulkActions.push({
                ...action,
                url: `${queryRoute}/bulk-delete`,
                useAjax: true,
                method: 'POST',
                requiresConfirmation: true,
                modalHeading: trans('actions::actions.buttons.delete') + ' ' + pluralLabel,
                modalDescription: trans('actions::actions.confirm_bulk_delete_description'),
                modalSubmitActionLabel: trans('actions::actions.buttons.delete'),
                modalCancelActionLabel: trans('actions::actions.buttons.cancel'),
            });
        } else {
            configuredBulkActions.push({
                ...action,
                useAjax: true,
            });
        }
    });

    // Fetch relation records
    const fetchRecords = async () => {
        setIsLoading(true);
        try {
            const response = await fetch(queryRoute, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (response.ok) {
                const data = await response.json();
                setRecords(data.data || []);
                setPagination((previous: any) => data.pagination || previous);
            }
        } catch (error) {
            console.error('Failed to fetch relation records:', error);
        } finally {
            setIsLoading(false);
        }
    };

    // Refresh records after action completion
    const handleActionComplete = () => {
        fetchRecords();
    };

    // Handle data loaded from Table component (for sorting/pagination)
    const handleDataLoaded = (data: { records: any[]; pagination: any }) => {
        setRecords(data.records);
        setPagination(data.pagination);
    };

    useEffect(() => {
        fetchRecords();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const IconComponent = icon ? resolveIcon(icon) : null;
    const EmptyIcon = IconComponent || Inbox;

    return (
        <div className="relation-manager">
            {/* Relation Manager Header */}
            <div className="flex items-center justify-between mb-4">
                <div className="flex items-center gap-2">
                    {IconComponent && <IconComponent className="h-5 w-5 text-muted-foreground" />}
                    <h3 className="text-lg font-semibold">{pluralLabel}</h3>
                </div>

                {/* Header Actions (Create button with modal) */}
                <div className="flex items-center gap-2">
                    {configuredHeaderActions.map((action) => (
                        <ActionButton key={action.name} {...action} onActionComplete={handleActionComplete} />
                    ))}
                </div>
            </div>

            {isLoading ? (
                // Loading State (Shimmer Skeleton)
                <div className="space-y-4">
                    {/* Table header skeleton */}
                    <div className="flex items-center justify-between gap-4 py-2">
                        <div className="h-9 w-64 bg-muted/60 rounded animate-pulse"></div>
                        <div className="h-9 w-32 bg-muted/60 rounded animate-pulse"></div>
                    </div>
                    {/* Table rows skeleton */}
                    <div className="border rounded-lg overflow-hidden">
                        <div className="bg-muted/30 px-4 py-3 flex gap-4">
                            <div className="h-4 w-1/4 bg-muted/60 rounded animate-pulse"></div>
                            <div className="h-4 w-1/4 bg-muted/60 rounded animate-pulse" style={{ animationDelay: '75ms' }}></div>
                            <div className="h-4 w-1/4 bg-muted/60 rounded animate-pulse" style={{ animationDelay: '150ms' }}></div>
                            <div className="h-4 w-1/4 bg-muted/60 rounded animate-pulse" style={{ animationDelay: '225ms' }}></div>
                        </div>
                        {skeletonRows.map((i) => (
                            <div key={i} className="px-4 py-3 flex gap-4 border-t">
                                <div className="h-4 w-1/4 bg-muted/60 rounded animate-pulse" style={{ animationDelay: `${i * 50}ms` }}></div>
                                <div className="h-4 w-1/4 bg-muted/60 rounded animate-pulse" style={{ animationDelay: `${i * 50 + 75}ms` }}></div>
                                <div className="h-4 w-1/4 bg-muted/60 rounded animate-pulse" style={{ animationDelay: `${i * 50 + 150}ms` }}></div>
                                <div className="h-4 w-1/4 bg-muted/60 rounded animate-pulse" style={{ animationDelay: `${i * 50 + 225}ms` }}></div>
                            </div>
                        ))}
                    </div>
                </div>
            ) : records.length > 0 ? (
                // Table
                <div>
                    <Table
                        table={table}
                        records={records}
                        pagination={pagination}
                        recordActions={configuredRecordActions}
                        bulkActions={configuredBulkActions}
                        resourceSlug={resourceSlug}
                        queryRoute={queryRoute}
                        currentView="table"
                        useAjax={true}
                        relationContext={{
                            baseUrl: queryRoute,
                            relationship,
                            canEdit,
                            canDelete,
                            columnExecutionRoute: `${queryRoute}/__ID__/column`,
                        }}
                        onDataLoaded={handleDataLoaded}
                        onActionComplete={handleActionComplete}
                    />
                </div>
            ) : (
                // Empty State
                <div className="flex flex-col items-center justify-center py-12 text-center border border-dashed rounded-lg">
                    <EmptyIcon className="h-12 w-12 text-muted-foreground mb-4" />
                    <h4 className="text-sm font-medium text-muted-foreground">{trans('tables::tables.columns.no_data')}</h4>
                    {configuredHeaderActions.length > 0 && (
                        <div className="mt-4">
                            {configuredHeaderActions.map((action) => (
                                <ActionButton key={action.name} {...action} onActionComplete={handleActionComplete} />
                            ))}
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
