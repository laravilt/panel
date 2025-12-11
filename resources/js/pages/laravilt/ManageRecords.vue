<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import ManageRecordsComponent from '@laravilt/panel/components/ManageRecords.vue'
import PanelLayout from '@laravilt/panel/layouts/PanelLayout.vue'
import { computed, markRaw } from 'vue'

const PanelLayoutRaw = markRaw(PanelLayout)

interface BreadcrumbItem {
    label: string
    url: string | null
}

const props = defineProps<{
    page: {
        heading: string
        subheading?: string | null
        headerActions: any[]
        actionUrl?: string
    }
    breadcrumbs?: BreadcrumbItem[]
    panelId?: string
    resourceSlug: string
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
}>()

// Use breadcrumbs from props (backend) or fallback to simple default
const breadcrumbs = computed<BreadcrumbItem[]>(() => {
    if (props.breadcrumbs && props.breadcrumbs.length > 0) {
        return props.breadcrumbs
    }
    // Fallback: simple Dashboard → Current Page
    return [
        {
            label: 'Dashboard',
            url: `/${props.panelId}`,
        },
        {
            label: props.page.heading,
            url: null,
        },
    ]
})

// Transform breadcrumbs to frontend format (label/url → title/href)
const transformedBreadcrumbs = computed(() => {
    return breadcrumbs.value.map(item => ({
        title: item.label,
        href: item.url || '#',
    }))
})
</script>

<template>
    <Head :title="page.heading" />

    <component :is="PanelLayoutRaw" :breadcrumbs="transformedBreadcrumbs">
        <div class="flex flex-1 flex-col gap-4 p-4 min-h-0 overflow-hidden max-h-[calc(100vh-4rem)]">
            <!-- Page Content Area -->
            <div class="flex-1 min-h-0 flex flex-col overflow-y-auto">
                <ManageRecordsComponent
                    :resource-slug="resourceSlug"
                    :panel-id="panelId || ''"
                    :label="label"
                    :plural-label="pluralLabel"
                    :icon="icon"
                    :can-view="canView"
                    :can-create="canCreate"
                    :can-edit="canEdit"
                    :can-delete="canDelete"
                    :form="form"
                    :infolist="infolist"
                    :table="table"
                    :header-actions="headerActions"
                    :query-route="queryRoute"
                />
            </div>
        </div>
    </component>
</template>
