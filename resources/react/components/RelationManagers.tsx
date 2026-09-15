import { useRef, useState, type KeyboardEvent } from 'react';
import { cn } from '@/lib/utils';
import { resolveIcon } from '@laravilt/support/lib/icons';
import RelationManager from './RelationManager';

export interface RelationManagerData {
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
}

export interface RelationManagersProps {
    relationManagers: RelationManagerData[];
    ownerRecordId: string | number;
    resourceSlug: string;
    panelId: string;
}

export default function RelationManagers({ relationManagers, ownerRecordId, resourceSlug, panelId: panelIdProp }: RelationManagersProps) {
    // Active tab state
    const [activeTab, setActiveTab] = useState<string>(relationManagers[0]?.relationship || '');

    // Tab items
    const tabs = relationManagers.map((rm) => ({
        key: rm.relationship,
        label: rm.pluralLabel,
        icon: rm.icon,
    }));

    // Tabs a11y: ids, roving tabindex and arrow-key navigation
    const tabsId = `relation-managers-${resourceSlug}-${ownerRecordId}`;
    const tabId = (key: string) => `${tabsId}-tab-${key}`;
    const panelId = (key: string) => `${tabsId}-panel-${key}`;
    const tabRefs = useRef<(HTMLButtonElement | null)[]>([]);

    const onTabKeydown = (event: KeyboardEvent<HTMLButtonElement>, index: number) => {
        const count = tabs.length;
        if (count === 0) return;
        const isRtl = event.currentTarget.closest('[dir="rtl"]') !== null;
        let next: number;
        switch (event.key) {
            case 'ArrowRight':
                next = isRtl ? index - 1 : index + 1;
                break;
            case 'ArrowLeft':
                next = isRtl ? index + 1 : index - 1;
                break;
            case 'Home':
                next = 0;
                break;
            case 'End':
                next = count - 1;
                break;
            default:
                return;
        }
        event.preventDefault();
        next = (next + count) % count;
        setActiveTab(tabs[next].key);
        tabRefs.current[next]?.focus();
    };

    // Current relation manager
    const currentRelationManager = relationManagers.find((rm) => rm.relationship === activeTab);

    if (!(relationManagers && relationManagers.length > 0)) {
        return null;
    }

    return (
        <div className="relation-managers mt-8">
            {/* Tabs Header */}
            <div className="border-b">
                <div className="flex space-x-4 overflow-x-auto" role="tablist" aria-label="Relation Tabs">
                    {tabs.map((tab, index) => {
                        const TabIcon = tab.icon ? resolveIcon(tab.icon) : null;

                        return (
                            <button
                                key={tab.key}
                                ref={(el) => {
                                    tabRefs.current[index] = el;
                                }}
                                type="button"
                                role="tab"
                                id={tabId(tab.key)}
                                aria-selected={activeTab === tab.key}
                                aria-controls={panelId(tab.key)}
                                tabIndex={activeTab === tab.key ? 0 : -1}
                                onClick={() => setActiveTab(tab.key)}
                                onKeyDown={(event) => onTabKeydown(event, index)}
                                className={cn(
                                    'flex items-center gap-2 px-4 py-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap',
                                    activeTab === tab.key
                                        ? 'border-primary text-primary'
                                        : 'border-transparent text-muted-foreground hover:text-foreground hover:border-muted-foreground/50',
                                )}
                            >
                                {TabIcon && <TabIcon className="h-4 w-4" />}
                                {tab.label}
                            </button>
                        );
                    })}
                </div>
            </div>

            {/* Tab Content */}
            {currentRelationManager && (
                <div
                    className="py-6"
                    role="tabpanel"
                    id={panelId(currentRelationManager.relationship)}
                    aria-labelledby={tabId(currentRelationManager.relationship)}
                    tabIndex={0}
                >
                    <RelationManager
                        key={currentRelationManager.relationship}
                        relationship={currentRelationManager.relationship}
                        label={currentRelationManager.label}
                        pluralLabel={currentRelationManager.pluralLabel}
                        icon={currentRelationManager.icon}
                        recordTitleAttribute={currentRelationManager.recordTitleAttribute}
                        readOnly={currentRelationManager.readOnly}
                        canCreate={currentRelationManager.canCreate}
                        canEdit={currentRelationManager.canEdit}
                        canDelete={currentRelationManager.canDelete}
                        form={currentRelationManager.form}
                        infolist={currentRelationManager.infolist}
                        table={currentRelationManager.table}
                        headerActions={currentRelationManager.headerActions}
                        ownerRecordId={ownerRecordId}
                        resourceSlug={resourceSlug}
                        panelId={panelIdProp}
                    />
                </div>
            )}
        </div>
    );
}
