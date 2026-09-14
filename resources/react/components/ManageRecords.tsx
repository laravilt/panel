import { useEffect, useState } from 'react';
import ActionButton from '@laravilt/actions/components/ActionButton';
import { resolveIcon } from '@laravilt/support/lib/icons';
import Table from '@laravilt/tables/components/Table';

export interface ManageRecordsProps {
    resourceSlug: string;
    panelId: string;
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

const skeletonRows = Array.from({ length: 8 }, (_, index) => index + 1);

export default function ManageRecords({
    resourceSlug,
    label,
    pluralLabel,
    icon,
    table,
    headerActions,
    queryRoute,
}: ManageRecordsProps) {
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

    // Fetch records
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
            console.error('Failed to fetch records:', error);
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

    return (
        <div className="manage-records">
            {/* Header */}
            <div className="flex items-center justify-between mb-6">
                <div className="flex items-center gap-3">
                    {IconComponent && <IconComponent className="h-6 w-6 text-muted-foreground" />}
                    <div>
                        <h2 className="text-2xl font-bold tracking-tight">{pluralLabel}</h2>
                    </div>
                </div>

                {/* Header Actions */}
                <div className="flex items-center gap-2">
                    {(headerActions || []).map((action: any) => (
                        <ActionButton key={action.name} {...action} onActionComplete={handleActionComplete} />
                    ))}
                </div>
            </div>

            {isLoading ? (
                // Loading State (Shimmer Skeleton)
                <div className="space-y-4">
                    {/* Toolbar skeleton */}
                    <div className="flex items-center justify-between gap-4 py-2">
                        <div className="h-10 w-80 bg-muted/60 rounded animate-pulse"></div>
                        <div className="flex gap-2">
                            <div className="h-10 w-24 bg-muted/60 rounded animate-pulse" style={{ animationDelay: '50ms' }}></div>
                            <div className="h-10 w-24 bg-muted/60 rounded animate-pulse" style={{ animationDelay: '100ms' }}></div>
                        </div>
                    </div>
                    {/* Table skeleton */}
                    <div className="border rounded-lg overflow-hidden">
                        <div className="bg-muted/30 px-4 py-3 flex gap-4">
                            <div className="h-4 w-8 bg-muted/60 rounded animate-pulse"></div>
                            <div className="h-4 w-1/5 bg-muted/60 rounded animate-pulse" style={{ animationDelay: '50ms' }}></div>
                            <div className="h-4 w-1/5 bg-muted/60 rounded animate-pulse" style={{ animationDelay: '100ms' }}></div>
                            <div className="h-4 w-1/5 bg-muted/60 rounded animate-pulse" style={{ animationDelay: '150ms' }}></div>
                            <div className="h-4 w-1/5 bg-muted/60 rounded animate-pulse" style={{ animationDelay: '200ms' }}></div>
                            <div className="h-4 w-20 bg-muted/60 rounded animate-pulse" style={{ animationDelay: '250ms' }}></div>
                        </div>
                        {skeletonRows.map((i) => (
                            <div key={i} className="px-4 py-4 flex gap-4 border-t">
                                <div className="h-4 w-8 bg-muted/60 rounded animate-pulse" style={{ animationDelay: `${i * 30}ms` }}></div>
                                <div className="h-4 w-1/5 bg-muted/60 rounded animate-pulse" style={{ animationDelay: `${i * 30 + 50}ms` }}></div>
                                <div className="h-4 w-1/5 bg-muted/60 rounded animate-pulse" style={{ animationDelay: `${i * 30 + 100}ms` }}></div>
                                <div className="h-4 w-1/5 bg-muted/60 rounded animate-pulse" style={{ animationDelay: `${i * 30 + 150}ms` }}></div>
                                <div className="h-4 w-1/5 bg-muted/60 rounded animate-pulse" style={{ animationDelay: `${i * 30 + 200}ms` }}></div>
                                <div className="h-4 w-20 bg-muted/60 rounded animate-pulse" style={{ animationDelay: `${i * 30 + 250}ms` }}></div>
                            </div>
                        ))}
                    </div>
                    {/* Pagination skeleton */}
                    <div className="flex items-center justify-between py-2">
                        <div className="h-4 w-32 bg-muted/60 rounded animate-pulse"></div>
                        <div className="flex gap-2">
                            <div className="h-9 w-9 bg-muted/60 rounded animate-pulse"></div>
                            <div className="h-9 w-9 bg-muted/60 rounded animate-pulse" style={{ animationDelay: '50ms' }}></div>
                            <div className="h-9 w-9 bg-muted/60 rounded animate-pulse" style={{ animationDelay: '100ms' }}></div>
                            <div className="h-9 w-9 bg-muted/60 rounded animate-pulse" style={{ animationDelay: '150ms' }}></div>
                        </div>
                    </div>
                </div>
            ) : records.length > 0 ? (
                // Table
                <div>
                    <Table
                        table={table}
                        records={records}
                        pagination={pagination}
                        resourceSlug={resourceSlug}
                        queryRoute={queryRoute}
                        useAjax={true}
                        currentView="table"
                        onActionComplete={handleActionComplete}
                        onDataLoaded={handleDataLoaded}
                    />
                </div>
            ) : (
                // Empty State
                <div className="flex flex-col items-center justify-center py-16 border rounded-lg bg-muted/10">
                    {IconComponent && <IconComponent className="h-12 w-12 text-muted-foreground/50 mb-4" />}
                    <h3 className="text-lg font-medium text-muted-foreground">No {pluralLabel.toLowerCase()} found</h3>
                    <p className="text-sm text-muted-foreground/70 mt-1">Get started by creating a new {label.toLowerCase()}.</p>
                </div>
            )}
        </div>
    );
}
