<script setup lang="ts">
import { ref, computed } from 'vue'
import * as LucideIcons from 'lucide-vue-next'
import RelationManager from './RelationManager.vue'
import { useLocalization } from '@laravilt/support/composables'

const { trans } = useLocalization()

// Function to get Lucide icon component by name
const getIconComponent = (iconName: string | null | undefined) => {
    if (!iconName) return null
    // Convert icon name to PascalCase (e.g., 'star' -> 'Star', 'file-text' -> 'FileText')
    const pascalCase = iconName
        .split('-')
        .map(word => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
        .join('')
    return (LucideIcons as any)[pascalCase] || null
}

interface RelationManagerData {
    relationship: string
    label: string
    pluralLabel: string
    icon?: string | null
    recordTitleAttribute?: string | null
    readOnly: boolean
    canCreate: boolean
    canEdit: boolean
    canDelete: boolean
    form: any
    infolist?: any
    table: any
    headerActions?: any[]
}

interface RelationManagersProps {
    relationManagers: RelationManagerData[]
    ownerRecordId: string | number
    resourceSlug: string
    panelId: string
}

const props = defineProps<RelationManagersProps>()

// Active tab state
const activeTab = ref<string>(props.relationManagers[0]?.relationship || '')

// Computed for tab items
const tabs = computed(() => {
    return props.relationManagers.map(rm => ({
        key: rm.relationship,
        label: rm.pluralLabel,
        icon: rm.icon,
    }))
})

// Get current relation manager
const currentRelationManager = computed(() => {
    return props.relationManagers.find(rm => rm.relationship === activeTab.value)
})
</script>

<template>
    <div v-if="relationManagers && relationManagers.length > 0" class="relation-managers mt-8">
        <!-- Tabs Header -->
        <div class="border-b">
            <nav class="flex space-x-4 overflow-x-auto" aria-label="Relation Tabs">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    type="button"
                    @click="activeTab = tab.key"
                    :class="[
                        'flex items-center gap-2 px-4 py-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap',
                        activeTab === tab.key
                            ? 'border-primary text-primary'
                            : 'border-transparent text-muted-foreground hover:text-foreground hover:border-muted-foreground/50'
                    ]"
                >
                    <component
                        v-if="tab.icon && getIconComponent(tab.icon)"
                        :is="getIconComponent(tab.icon)"
                        class="h-4 w-4"
                    />
                    {{ tab.label }}
                </button>
            </nav>
        </div>

        <!-- Tab Content -->
        <div class="py-6">
            <RelationManager
                v-if="currentRelationManager"
                :key="currentRelationManager.relationship"
                :relationship="currentRelationManager.relationship"
                :label="currentRelationManager.label"
                :plural-label="currentRelationManager.pluralLabel"
                :icon="currentRelationManager.icon"
                :record-title-attribute="currentRelationManager.recordTitleAttribute"
                :read-only="currentRelationManager.readOnly"
                :can-create="currentRelationManager.canCreate"
                :can-edit="currentRelationManager.canEdit"
                :can-delete="currentRelationManager.canDelete"
                :form="currentRelationManager.form"
                :infolist="currentRelationManager.infolist"
                :table="currentRelationManager.table"
                :header-actions="currentRelationManager.headerActions"
                :owner-record-id="ownerRecordId"
                :resource-slug="resourceSlug"
                :panel-id="panelId"
            />
        </div>
    </div>
</template>
