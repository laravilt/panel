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

// Tabs a11y: ids, roving tabindex and arrow-key navigation
const tabsId = `relation-managers-${props.resourceSlug}-${props.ownerRecordId}`
const tabId = (key: string) => `${tabsId}-tab-${key}`
const tabPanelId = (key: string) => `${tabsId}-panel-${key}`
const tabRefs = ref<HTMLButtonElement[]>([])

const onTabKeydown = (event: KeyboardEvent, index: number) => {
    const count = tabs.value.length
    if (count === 0) return
    const isRtl = (event.currentTarget as HTMLElement | null)?.closest('[dir="rtl"]') !== null
    let next: number | null = null
    switch (event.key) {
        case 'ArrowRight':
            next = isRtl ? index - 1 : index + 1
            break
        case 'ArrowLeft':
            next = isRtl ? index + 1 : index - 1
            break
        case 'Home':
            next = 0
            break
        case 'End':
            next = count - 1
            break
        default:
            return
    }
    event.preventDefault()
    next = (next + count) % count
    activeTab.value = tabs.value[next].key
    tabRefs.value[next]?.focus()
}

// Get current relation manager
const currentRelationManager = computed(() => {
    return props.relationManagers.find(rm => rm.relationship === activeTab.value)
})
</script>

<template>
    <div v-if="relationManagers && relationManagers.length > 0" class="relation-managers mt-8">
        <!-- Tabs Header -->
        <div class="border-b">
            <div class="flex space-x-4 overflow-x-auto" role="tablist" aria-label="Relation Tabs">
                <button
                    v-for="(tab, index) in tabs"
                    :key="tab.key"
                    :ref="(el) => { if (el) tabRefs[index] = el as HTMLButtonElement }"
                    type="button"
                    role="tab"
                    :id="tabId(tab.key)"
                    :aria-selected="activeTab === tab.key ? 'true' : 'false'"
                    :aria-controls="tabPanelId(tab.key)"
                    :tabindex="activeTab === tab.key ? 0 : -1"
                    @click="activeTab = tab.key"
                    @keydown="onTabKeydown($event, index)"
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
            </div>
        </div>

        <!-- Tab Content -->
        <div
            v-if="currentRelationManager"
            class="py-6"
            role="tabpanel"
            :id="tabPanelId(currentRelationManager.relationship)"
            :aria-labelledby="tabId(currentRelationManager.relationship)"
            tabindex="0"
        >
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
