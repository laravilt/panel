<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@laravilt/panel/components/PanelSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import { NotificationContainer } from '@laravilt/notifications/app.ts';
import { usePage } from '@inertiajs/vue3';
import { computed, onMounted, watch } from 'vue';

interface BreadcrumbItem {
    title: string;
    href: string;
}

interface Props {
    breadcrumbs?: BreadcrumbItem[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

// Get shared panel data from Inertia
const page = usePage();
const panelData = computed(() => (page.props?.panel ?? {}) as any);
const user = computed(() => page.props?.auth?.user as any);

// Panel Font Loading
const loadPanelFont = () => {
    const font = panelData.value?.font;
    if (font?.url && font?.family) {
        // Load font stylesheet
        const linkId = `panel-font-${font.family.replace(/\s+/g, '-').toLowerCase()}`;
        if (!document.getElementById(linkId)) {
            const link = document.createElement('link');
            link.id = linkId;
            link.rel = 'stylesheet';
            link.href = font.url;
            document.head.appendChild(link);
        }

        // Apply font family with !important to override Tailwind's @theme
        const fontValue = `"${font.family}", ui-sans-serif, system-ui, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol', 'Noto Color Emoji'`;
        document.documentElement.style.setProperty('--font-sans', fontValue);
        document.body.style.setProperty('font-family', fontValue, 'important');
        document.documentElement.style.setProperty('font-family', fontValue, 'important');
    }
};

onMounted(() => {
    loadPanelFont();
});

watch(panelData, () => {
    loadPanelFont();
});
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar
            :navigation="panelData?.navigation"
            :panel="panelData"
            :user="user"
        />
        <AppContent variant="sidebar" class="overflow-hidden">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <slot />
        </AppContent>
        <NotificationContainer />
    </AppShell>
</template>
