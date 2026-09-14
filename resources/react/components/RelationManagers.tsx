import { useState } from 'react';
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

export default function RelationManagers({ relationManagers, ownerRecordId, resourceSlug, panelId }: RelationManagersProps) {
    // Active tab state
    const [activeTab, setActiveTab] = useState<string>(relationManagers[0]?.relationship || '');

    // Tab items
    const tabs = relationManagers.map((rm) => ({
        key: rm.relationship,
        label: rm.pluralLabel,
        icon: rm.icon,
    }));

    // Current relation manager
    const currentRelationManager = relationManagers.find((rm) => rm.relationship === activeTab);

    if (!(relationManagers && relationManagers.length > 0)) {
        return null;
    }

    return (
        <div className="relation-managers mt-8">
            {/* Tabs Header */}
            <div className="border-b">
                <nav className="flex space-x-4 overflow-x-auto" aria-label="Relation Tabs">
                    {tabs.map((tab) => {
                        const TabIcon = tab.icon ? resolveIcon(tab.icon) : null;

                        return (
                            <button
                                key={tab.key}
                                type="button"
                                onClick={() => setActiveTab(tab.key)}
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
                </nav>
            </div>

            {/* Tab Content */}
            <div className="py-6">
                {currentRelationManager && (
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
                        panelId={panelId}
                    />
                )}
            </div>
        </div>
    );
}
