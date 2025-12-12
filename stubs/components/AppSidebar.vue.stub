<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
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
import { dashboard as adminDashboard } from '@/routes/admin';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { BookOpen, Folder, LayoutGrid, Home } from 'lucide-vue-next';
import * as LucideIcons from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';
import { computed, type Component } from 'vue';

const page = usePage();

// Get panel data from Inertia shared props
const panel = computed(() => page.props.panel as any);

// Default navigation items for non-panel pages
const defaultMainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: adminDashboard(),
        icon: LayoutGrid,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Github Repo',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: Folder,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#vue',
        icon: BookOpen,
    },
];

// Convert panel navigation to NavItem format
const mainNavItems = computed<NavItem[]>(() => {
    if (!panel.value?.navigation || !Array.isArray(panel.value.navigation)) {
        return defaultMainNavItems;
    }

    // Map navigation items (each item can be a simple nav item or a group with items)
    return panel.value.navigation.flatMap((navItem: any) => {
        // If it's a group with items
        if (navItem.items && Array.isArray(navItem.items)) {
            return navItem.items.map((item: any) => ({
                title: item.title,
                href: item.url,
                url: item.url,
                icon: getIconComponent(item.icon),
                badge: item.badge || item.navigationBadge,
                badgeCount: item.badgeCount || item.navigationBadgeCount,
                badgeColor: item.badgeColor || item.navigationBadgeColor,
            }));
        }

        // If it's a direct nav item
        return [{
            title: navItem.title,
            href: navItem.url,
            url: navItem.url,
            icon: getIconComponent(navItem.icon),
            badge: navItem.badge || navItem.navigationBadge,
            badgeCount: navItem.badgeCount || navItem.navigationBadgeCount,
            badgeColor: navItem.badgeColor || navItem.navigationBadgeColor,
        }];
    });
});

// Get Lucide icon component from icon name
const getIconComponent = (iconName: string | null | undefined): Component => {
    if (!iconName) return Home;

    // If it starts with 'heroicon-o-', map it to Lucide (legacy support)
    if (iconName.startsWith('heroicon-o-')) {
        const iconMap: Record<string, string> = {
            'heroicon-o-home': 'Home',
            'heroicon-o-user': 'User',
            'heroicon-o-users': 'Users',
            'heroicon-o-cog': 'Settings',
            'heroicon-o-chart-bar': 'BarChart',
            'heroicon-o-document-text': 'FileText',
            'heroicon-o-folder': 'Folder',
            'heroicon-o-shopping-cart': 'ShoppingCart',
        };

        const lucideName = iconMap[iconName] || 'LayoutGrid';
        return (LucideIcons as any)[lucideName] || Home;
    }

    // Try to use it as a Lucide icon name directly
    return (LucideIcons as any)[iconName] || Home;
};

// Dashboard URL - use panel path if available
const dashboardHref = computed(() => {
    if (panel.value?.path) {
        return `/${panel.value.path}`;
    }
    return adminDashboard();
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboardHref">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
