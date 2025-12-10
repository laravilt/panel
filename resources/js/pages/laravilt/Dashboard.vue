<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, markRaw } from 'vue';
import PanelLayout from '../../layouts/PanelLayout.vue';
import WidgetRenderer from '@laravilt/widgets/components/WidgetRenderer.vue';

const PanelLayoutRaw = markRaw(PanelLayout);

interface BreadcrumbItem {
    label: string;
    url: string | null;
}

interface WidgetData {
    component: string;
    stats?: any[];
    columns?: number;
    [key: string]: any;
}

const props = defineProps<{
    title?: string;
    breadcrumbs?: BreadcrumbItem[];
    headerWidgets?: WidgetData[];
    footerWidgets?: WidgetData[];
}>();

// Transform breadcrumbs to frontend format
const transformedBreadcrumbs = computed(() => {
    if (!props.breadcrumbs) return [];
    return props.breadcrumbs.map(item => ({
        title: item.label,
        href: item.url || '#',
    }));
});
</script>

<template>
    <Head :title="title || 'Dashboard'" />

    <PanelLayoutRaw :breadcrumbs="transformedBreadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4">
            <!-- Header Widgets -->
            <WidgetRenderer
                v-if="headerWidgets && headerWidgets.length"
                :widgets="headerWidgets"
            />

            <!-- Main Content Area (for extending) -->
            <slot />

            <!-- Footer Widgets -->
            <WidgetRenderer
                v-if="footerWidgets && footerWidgets.length"
                :widgets="footerWidgets"
            />
        </div>
    </PanelLayoutRaw>
</template>
