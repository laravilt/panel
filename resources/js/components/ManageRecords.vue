<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import Table from '@laravilt/tables/components/Table.vue'
import ActionButton from '@laravilt/actions/components/ActionButton.vue'
import * as LucideIcons from 'lucide-vue-next'

// Function to get Lucide icon component by name
const getIconComponent = (iconName: string | null | undefined) => {
    if (!iconName) return null
    const pascalCase = iconName
        .split('-')
        .map(word => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
        .join('')
    return (LucideIcons as any)[pascalCase] || null
}

interface ManageRecordsProps {
    resourceSlug: string
    panelId: string
    label: string
    pluralLabel: string
    icon?: string | null
    canView: boolean
    canCreate: boolean
    canEdit: boolean
    canDelete: boolean
    form: any
    infolist?: any
    table: any
    headerActions?: any[]
    queryRoute: string
}

const props = defineProps<ManageRecordsProps>()

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

// Fetch records
const fetchRecords = async () => {
    isLoading.value = true
    try {
        const response = await fetch(props.queryRoute, {
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
        console.error('Failed to fetch records:', error)
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
    <div class="manage-records">
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-3">
                <component
                    v-if="icon && getIconComponent(icon)"
                    :is="getIconComponent(icon)"
                    class="h-6 w-6 text-muted-foreground"
                />
                <div>
                    <h2 class="text-2xl font-bold tracking-tight">{{ pluralLabel }}</h2>
                </div>
            </div>

            <!-- Header Actions -->
            <div class="flex items-center gap-2">
                <ActionButton
                    v-for="action in headerActions"
                    :key="action.name"
                    v-bind="action"
                    @action-complete="handleActionComplete"
                />
            </div>
        </div>

        <!-- Loading State (Shimmer Skeleton) -->
        <div v-if="isLoading" class="space-y-4">
            <!-- Toolbar skeleton -->
            <div class="flex items-center justify-between gap-4 py-2">
                <div class="h-10 w-80 bg-muted/60 rounded animate-pulse"></div>
                <div class="flex gap-2">
                    <div class="h-10 w-24 bg-muted/60 rounded animate-pulse" style="animation-delay: 50ms"></div>
                    <div class="h-10 w-24 bg-muted/60 rounded animate-pulse" style="animation-delay: 100ms"></div>
                </div>
            </div>
            <!-- Table skeleton -->
            <div class="border rounded-lg overflow-hidden">
                <div class="bg-muted/30 px-4 py-3 flex gap-4">
                    <div class="h-4 w-8 bg-muted/60 rounded animate-pulse"></div>
                    <div class="h-4 w-1/5 bg-muted/60 rounded animate-pulse" style="animation-delay: 50ms"></div>
                    <div class="h-4 w-1/5 bg-muted/60 rounded animate-pulse" style="animation-delay: 100ms"></div>
                    <div class="h-4 w-1/5 bg-muted/60 rounded animate-pulse" style="animation-delay: 150ms"></div>
                    <div class="h-4 w-1/5 bg-muted/60 rounded animate-pulse" style="animation-delay: 200ms"></div>
                    <div class="h-4 w-20 bg-muted/60 rounded animate-pulse" style="animation-delay: 250ms"></div>
                </div>
                <div v-for="i in 8" :key="i" class="px-4 py-4 flex gap-4 border-t">
                    <div class="h-4 w-8 bg-muted/60 rounded animate-pulse" :style="{ animationDelay: `${i * 30}ms` }"></div>
                    <div class="h-4 w-1/5 bg-muted/60 rounded animate-pulse" :style="{ animationDelay: `${i * 30 + 50}ms` }"></div>
                    <div class="h-4 w-1/5 bg-muted/60 rounded animate-pulse" :style="{ animationDelay: `${i * 30 + 100}ms` }"></div>
                    <div class="h-4 w-1/5 bg-muted/60 rounded animate-pulse" :style="{ animationDelay: `${i * 30 + 150}ms` }"></div>
                    <div class="h-4 w-1/5 bg-muted/60 rounded animate-pulse" :style="{ animationDelay: `${i * 30 + 200}ms` }"></div>
                    <div class="h-4 w-20 bg-muted/60 rounded animate-pulse" :style="{ animationDelay: `${i * 30 + 250}ms` }"></div>
                </div>
            </div>
            <!-- Pagination skeleton -->
            <div class="flex items-center justify-between py-2">
                <div class="h-4 w-32 bg-muted/60 rounded animate-pulse"></div>
                <div class="flex gap-2">
                    <div class="h-9 w-9 bg-muted/60 rounded animate-pulse"></div>
                    <div class="h-9 w-9 bg-muted/60 rounded animate-pulse" style="animation-delay: 50ms"></div>
                    <div class="h-9 w-9 bg-muted/60 rounded animate-pulse" style="animation-delay: 100ms"></div>
                    <div class="h-9 w-9 bg-muted/60 rounded animate-pulse" style="animation-delay: 150ms"></div>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div v-else-if="records.length > 0">
            <Table
                :table="table"
                :records="records"
                :pagination="pagination"
                :resource-slug="resourceSlug"
                :query-route="queryRoute"
                :use-ajax="true"
                current-view="table"
                @action-complete="handleActionComplete"
                @data-loaded="handleDataLoaded"
            />
        </div>

        <!-- Empty State -->
        <div v-else class="flex flex-col items-center justify-center py-16 border rounded-lg bg-muted/10">
            <component
                v-if="icon && getIconComponent(icon)"
                :is="getIconComponent(icon)"
                class="h-12 w-12 text-muted-foreground/50 mb-4"
            />
            <h3 class="text-lg font-medium text-muted-foreground">No {{ pluralLabel.toLowerCase() }} found</h3>
            <p class="text-sm text-muted-foreground/70 mt-1">Get started by creating a new {{ label.toLowerCase() }}.</p>
        </div>
    </div>
</template>
