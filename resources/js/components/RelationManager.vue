<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import Table from '@laravilt/tables/components/Table.vue'
import ActionButton from '@laravilt/actions/components/ActionButton.vue'
import { useLocalization } from '@laravilt/support/composables'
import * as LucideIcons from 'lucide-vue-next'

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

interface RelationManagerProps {
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
    ownerRecordId: string | number
    resourceSlug: string
    panelId: string
}

const props = defineProps<RelationManagerProps>()

// State for records
const records = ref<any[]>([])
const isLoading = ref(true)
const pagination = ref({
    total: 0,
    per_page: 10,
    current_page: 1,
    last_page: 1,
    from: 0,
    to: 0,
})

// Build the query route for the relation
const queryRoute = computed(() => {
    return `/${props.panelId}/${props.resourceSlug}/${props.ownerRecordId}/relations/${props.relationship}`
})

// Configure header actions with the correct URL for submission
const configuredHeaderActions = computed(() => {
    if (!props.headerActions) return []

    return props.headerActions.map(action => ({
        ...action,
        url: queryRoute.value, // Set URL for the action to submit to
        useAjax: true, // Use fetch instead of Inertia to avoid page reload
    }))
})

// Configure record actions with proper URLs for view/edit/delete
const configuredRecordActions = computed(() => {
    const actions: any[] = []

    // Get record actions from table config
    const tableRecordActions = props.table?.recordActions || []

    // Process each action and configure URLs
    tableRecordActions.forEach((action: any) => {
        if (action.name === 'view') {
            actions.push({
                ...action,
                // URL will be set per-record in the Table component
                useAjax: true,
                requiresConfirmation: true,
                modalHeading: trans('actions::actions.buttons.view') + ' ' + props.label,
                // Use infolist schema if available, otherwise fall back to form schema
                modalInfolistSchema: props.infolist?.schema || null,
                modalFormSchema: props.infolist?.schema ? null : props.form?.schema || [],
                modalSubmitActionLabel: null, // No submit button for view
                modalCancelActionLabel: trans('actions::actions.buttons.close'),
                isViewOnly: true, // Flag to indicate view-only mode
                modalWidth: 'lg', // Larger modal for view
            })
        } else if (action.name === 'edit' && props.canEdit) {
            actions.push({
                ...action,
                // URL will be set per-record in the Table component
                useAjax: true,
                method: 'PUT',
                requiresConfirmation: true,
                modalHeading: trans('actions::actions.buttons.edit') + ' ' + props.label,
                modalFormSchema: props.form?.schema || [],
                modalSubmitActionLabel: trans('actions::actions.buttons.save'),
                modalCancelActionLabel: trans('actions::actions.buttons.cancel'),
                modalWidth: 'lg', // Larger modal for edit
            })
        } else if (action.name === 'delete' && props.canDelete) {
            actions.push({
                ...action,
                useAjax: true,
                method: 'DELETE',
                requiresConfirmation: true,
                modalHeading: trans('actions::actions.buttons.delete') + ' ' + props.label,
                modalDescription: trans('actions::actions.confirm_delete_description'),
                modalSubmitActionLabel: trans('actions::actions.buttons.delete'),
                modalCancelActionLabel: trans('actions::actions.buttons.cancel'),
            })
        } else {
            // Pass through other actions as-is
            actions.push({
                ...action,
                useAjax: true,
            })
        }
    })

    return actions
})

// Configure bulk actions with proper URL
const configuredBulkActions = computed(() => {
    const actions: any[] = []

    // Get bulk actions from table config
    const tableBulkActions = props.table?.bulkActions || []

    tableBulkActions.forEach((action: any) => {
        if (action.name === 'delete' || action.name === 'bulk-delete') {
            actions.push({
                ...action,
                url: `${queryRoute.value}/bulk-delete`,
                useAjax: true,
                method: 'POST',
                requiresConfirmation: true,
                modalHeading: trans('actions::actions.buttons.delete') + ' ' + props.pluralLabel,
                modalDescription: trans('actions::actions.confirm_bulk_delete_description'),
                modalSubmitActionLabel: trans('actions::actions.buttons.delete'),
                modalCancelActionLabel: trans('actions::actions.buttons.cancel'),
            })
        } else {
            actions.push({
                ...action,
                useAjax: true,
            })
        }
    })

    return actions
})

// Fetch relation records
const fetchRecords = async () => {
    isLoading.value = true
    try {
        const response = await fetch(queryRoute.value, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })

        if (response.ok) {
            const data = await response.json()
            records.value = data.data || []
            pagination.value = data.pagination || pagination.value
        }
    } catch (error) {
        console.error('Failed to fetch relation records:', error)
    } finally {
        isLoading.value = false
    }
}

// Refresh records after action completion
const handleActionComplete = () => {
    fetchRecords()
}

// Handle data loaded from Table component (for sorting/pagination)
const handleDataLoaded = (data: { records: any[], pagination: any }) => {
    records.value = data.records
    pagination.value = data.pagination
}

onMounted(() => {
    fetchRecords()
})
</script>

<template>
    <div class="relation-manager">
        <!-- Relation Manager Header -->
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
                <component
                    v-if="icon && getIconComponent(icon)"
                    :is="getIconComponent(icon)"
                    class="h-5 w-5 text-muted-foreground"
                />
                <h3 class="text-lg font-semibold">{{ pluralLabel }}</h3>
            </div>

            <!-- Header Actions (Create button with modal) -->
            <div class="flex items-center gap-2">
                <ActionButton
                    v-for="action in configuredHeaderActions"
                    :key="action.name"
                    v-bind="action"
                    @action-complete="handleActionComplete"
                />
            </div>
        </div>

        <!-- Loading State (Shimmer Skeleton) -->
        <div v-if="isLoading" class="space-y-4">
            <!-- Table header skeleton -->
            <div class="flex items-center justify-between gap-4 py-2">
                <div class="h-9 w-64 bg-muted/60 rounded animate-pulse"></div>
                <div class="h-9 w-32 bg-muted/60 rounded animate-pulse"></div>
            </div>
            <!-- Table rows skeleton -->
            <div class="border rounded-lg overflow-hidden">
                <div class="bg-muted/30 px-4 py-3 flex gap-4">
                    <div class="h-4 w-1/4 bg-muted/60 rounded animate-pulse"></div>
                    <div class="h-4 w-1/4 bg-muted/60 rounded animate-pulse" style="animation-delay: 75ms"></div>
                    <div class="h-4 w-1/4 bg-muted/60 rounded animate-pulse" style="animation-delay: 150ms"></div>
                    <div class="h-4 w-1/4 bg-muted/60 rounded animate-pulse" style="animation-delay: 225ms"></div>
                </div>
                <div v-for="i in 5" :key="i" class="px-4 py-3 flex gap-4 border-t">
                    <div class="h-4 w-1/4 bg-muted/60 rounded animate-pulse" :style="{ animationDelay: `${i * 50}ms` }"></div>
                    <div class="h-4 w-1/4 bg-muted/60 rounded animate-pulse" :style="{ animationDelay: `${i * 50 + 75}ms` }"></div>
                    <div class="h-4 w-1/4 bg-muted/60 rounded animate-pulse" :style="{ animationDelay: `${i * 50 + 150}ms` }"></div>
                    <div class="h-4 w-1/4 bg-muted/60 rounded animate-pulse" :style="{ animationDelay: `${i * 50 + 225}ms` }"></div>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div v-else-if="records.length > 0">
            <Table
                :table="table"
                :records="records"
                :pagination="pagination"
                :record-actions="configuredRecordActions"
                :bulk-actions="configuredBulkActions"
                :resource-slug="resourceSlug"
                :query-route="queryRoute"
                current-view="table"
                :use-ajax="true"
                :relation-context="{ baseUrl: queryRoute, relationship, canEdit, canDelete, columnExecutionRoute: `${queryRoute}/__ID__/column` }"
                @data-loaded="handleDataLoaded"
                @action-complete="handleActionComplete"
            />
        </div>

        <!-- Empty State -->
        <div v-else class="flex flex-col items-center justify-center py-12 text-center border border-dashed rounded-lg">
            <component
                :is="getIconComponent(icon) || LucideIcons.Inbox"
                class="h-12 w-12 text-muted-foreground mb-4"
            />
            <h4 class="text-sm font-medium text-muted-foreground">
                {{ trans('table.no_data') }}
            </h4>
            <div v-if="configuredHeaderActions.length > 0" class="mt-4">
                <ActionButton
                    v-for="action in configuredHeaderActions"
                    :key="action.name"
                    v-bind="action"
                    @action-complete="handleActionComplete"
                />
            </div>
        </div>
    </div>
</template>
