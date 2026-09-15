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

// Panel custom assets (Panel::customCss() / customJs())
const ASSET_ATTRIBUTE = 'data-laravilt-asset';

const syncPanelAssets = () => {
    if (typeof document === 'undefined') {
        return;
    }

    const wanted = new Map<string, { type: 'css' | 'js'; url: string }>();
    const collect = (urls: unknown, type: 'css' | 'js') => {
        if (!Array.isArray(urls)) {
            return;
        }
        for (const url of urls) {
            if (typeof url === 'string' && url !== '') {
                wanted.set(`${type}:${url}`, { type, url });
            }
        }
    };
    collect(panelData.value?.customCss, 'css');
    collect(panelData.value?.customJs, 'js');

    // Remove assets that are no longer configured and keep the ones already injected
    document.head.querySelectorAll(`[${ASSET_ATTRIBUTE}]`).forEach((element) => {
        const key = element.getAttribute(ASSET_ATTRIBUTE) ?? '';
        if (wanted.has(key)) {
            wanted.delete(key);
        } else {
            element.remove();
        }
    });

    wanted.forEach(({ type, url }, key) => {
        let element: HTMLLinkElement | HTMLScriptElement;
        if (type === 'css') {
            element = document.createElement('link');
            element.rel = 'stylesheet';
            element.href = url;
        } else {
            element = document.createElement('script');
            element.src = url;
            element.defer = true;
        }
        element.setAttribute(ASSET_ATTRIBUTE, key);
        document.head.appendChild(element);
    });
};

onMounted(() => {
    loadPanelFont();
    syncPanelAssets();
});

watch(panelData, () => {
    loadPanelFont();
    syncPanelAssets();
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
