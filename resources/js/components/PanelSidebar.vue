<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { Link } from '@inertiajs/vue3';
import * as LucideIcons from 'lucide-vue-next';
import { computed, type Component } from 'vue';
import AppLogoIcon from './AppLogoIcon.vue';

// Props for component mode (when used via Blade bridge)
const props = defineProps<{
    navigation?: any[]
    panel?: {
        id: string
        path: string
        brandName: string
        brandLogo?: string
    }
    user?: {
        name: string
        email: string
    }
    collapsible?: boolean
    variant?: string
}>()

// Support prop-based panel data
const panel = computed(() => props.panel || {});
const panelNavigation = computed(() => {
    // Use component prop navigation if available
    const nav = props.navigation || panel.value.navigation || [];

    return nav.map((item: any) => ({
        type: item.type, // Preserve type field (item/group)
        title: item.title,
        href: item.url,
        url: item.url, // Include both href and url for compatibility
        icon: getIconComponent(item.icon),
        collapsed: item.collapsed, // Preserve collapsed state
        badge: item.badge, // Badge text
        badgeCount: item.badgeCount, // Badge count
        badgeColor: item.badgeColor, // Badge color
        items: item.items?.map((subItem: any) => ({
            type: subItem.type,
            title: subItem.title,
            href: subItem.url,
            url: subItem.url,
            icon: getIconComponent(subItem.icon),
            badge: subItem.badge,
            badgeCount: subItem.badgeCount,
            badgeColor: subItem.badgeColor,
        })),
    }));
});

// Get Lucide icon component from icon name
const getIconComponent = (iconName: string | null | undefined): Component => {
    if (!iconName) return LucideIcons.LayoutGrid;

    // If it starts with 'heroicon-o-', map it to Lucide
    if (iconName.startsWith('heroicon-o-')) {
        const iconMap: Record<string, string> = {
            'heroicon-o-home': 'Home',
            'heroicon-o-user': 'User',
            'heroicon-o-users': 'Users',
            'heroicon-o-user-group': 'Users',
            'heroicon-o-cog': 'Settings',
            'heroicon-o-chart-bar': 'BarChart',
            'heroicon-o-document-text': 'FileText',
            'heroicon-o-folder': 'Folder',
            'heroicon-o-shopping-cart': 'ShoppingCart',
        };

        const lucideName = iconMap[iconName] || 'LayoutGrid';
        return (LucideIcons as any)[lucideName] || LucideIcons.LayoutGrid;
    }

    // Try to use it as a Lucide icon name directly
    return (LucideIcons as any)[iconName] || LucideIcons.LayoutGrid;
};

const dashboardHref = computed(() => `/${panel.value.path || 'admin'}`);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboardHref">
                            <!-- Brand Logo -->
                            <div
                                class="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground"
                            >
                                <img
                                    v-if="panel.brandLogo"
                                    :src="panel.brandLogo"
                                    :alt="panel.brandName || 'Logo'"
                                    class="size-5 object-contain"
                                />
                                <AppLogoIcon
                                    v-else
                                    class="size-5 fill-current text-white dark:text-black"
                                />
                            </div>
                            <!-- Brand Name -->
                            <div class="ms-1 grid flex-1 text-sm">
                                <span class="mb-0.5 truncate leading-tight font-semibold text-start rtl:text-right">
                                    {{ panel.brandName || 'Admin Panel' }}
                                </span>
                            </div>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="panelNavigation" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser :user="user" />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
