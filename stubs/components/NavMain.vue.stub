<script setup lang="ts">
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
    SidebarMenuBadge,
    useSidebar,
} from '@/components/ui/sidebar';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Badge } from '@/components/ui/badge';
import { urlIsActive } from '@/lib/utils';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';
import { ref, onMounted, nextTick, computed } from 'vue';

const props = defineProps<{
    items: NavItem[];
}>();

// Get sidebar state to determine if collapsed to icon mode
const { state: sidebarState } = useSidebar();
const isCollapsed = computed(() => sidebarState.value === 'collapsed');

// Get badge variant based on color
function getBadgeVariant(color?: string): 'default' | 'secondary' | 'destructive' | 'outline' | 'primary' | 'success' | 'danger' | 'warning' | 'info' | 'gray' {
    switch (color) {
        case 'primary':
            return 'primary';
        case 'success':
        case 'green':
            return 'success';
        case 'danger':
        case 'red':
            return 'danger';
        case 'warning':
        case 'yellow':
        case 'orange':
            return 'warning';
        case 'info':
        case 'blue':
            return 'info';
        case 'secondary':
            return 'secondary';
        case 'gray':
            return 'gray';
        case 'outline':
            return 'outline';
        case 'destructive':
            return 'destructive';
        default:
            return 'default';
    }
}

const page = usePage();

// Computed current URL for reactivity
const currentUrl = computed(() => page.url);

// Extract path from URL (handles both full URLs and relative paths)
function extractPath(url: string): string {
    if (!url) return '';
    try {
        if (url.startsWith('http://') || url.startsWith('https://')) {
            const urlObj = new URL(url);
            return urlObj.pathname;
        }
        // Remove query string and hash
        return url.split('?')[0].split('#')[0];
    } catch {
        return url;
    }
}

// Check if a nav item is active (handles regular URL matching and prefix matching for clusters)
// Uses computed currentUrl for proper reactivity
const isNavItemActive = (item: NavItem): boolean => {
    const url = currentUrl.value;
    const currentPath = extractPath(url);

    // First check activeMatchPrefix for cluster-style matching
    if (item.activeMatchPrefix) {
        const prefix = extractPath(item.activeMatchPrefix).replace(/\/$/, '');
        const normalizedCurrent = currentPath.replace(/\/$/, '');

        // Check if current URL starts with the prefix
        if (normalizedCurrent.startsWith(prefix) &&
            (normalizedCurrent[prefix.length] === '/' ||
             normalizedCurrent.length === prefix.length)) {
            return true;
        }
    }

    // Fall back to regular URL matching
    return urlIsActive(item.url || item.href, url);
};

// Track open state for each group
const openGroups = ref<Record<string, boolean>>({});

// Track which groups should animate (only after user click)
const animatingGroups = ref<Set<string>>(new Set());

// Load saved group states from localStorage
onMounted(() => {
    const saved = localStorage.getItem('navigation-groups-state');
    if (saved) {
        try {
            openGroups.value = JSON.parse(saved);
        } catch (e) {
            // Ignore parse errors
        }
    }

    // Initialize any groups that don't have saved state
    props.items.forEach(item => {
        if (item.type === 'group' && !(item.title in openGroups.value)) {
            openGroups.value[item.title] = !item.collapsed;
        }
    });
});

// Toggle group with animation (only called on user click)
function toggleGroup(title: string, isOpen: boolean) {
    // Mark this group for animation BEFORE state change
    animatingGroups.value.add(title);

    // Use nextTick to ensure data-animating is applied before state changes
    nextTick(() => {
        // Update state
        openGroups.value[title] = isOpen;
        localStorage.setItem('navigation-groups-state', JSON.stringify(openGroups.value));

        // Remove animation flag after animation completes
        setTimeout(() => {
            animatingGroups.value.delete(title);
        }, 250);
    });
}

// Check if a group should animate
function shouldAnimate(title: string): boolean {
    return animatingGroups.value.has(title);
}
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarMenu class="mt-6">
            <template v-for="item in items" :key="item.title">
                <!-- Navigation Group with nested items - Dropdown mode when collapsed -->
                <SidebarMenuItem
                    v-if="item.type === 'group' && item.items && item.items.length > 0 && isCollapsed"
                >
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <SidebarMenuButton :tooltip="item.title">
                                <component v-if="item.icon" :is="item.icon" />
                                <span>{{ item.title }}</span>
                                <ChevronRight
                                    class="ms-auto transition-transform duration-200 rtl:-rotate-180"
                                />
                            </SidebarMenuButton>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent
                            side="right"
                            align="start"
                            :side-offset="8"
                            class="min-w-56"
                        >
                            <DropdownMenuItem
                                v-for="subItem in item.items"
                                :key="subItem.title"
                                as-child
                            >
                                <Link
                                    :href="subItem.url"
                                    class="flex items-center justify-between w-full cursor-pointer"
                                    :class="urlIsActive(subItem.url, page.url) ? 'bg-accent' : ''"
                                >
                                    <span class="flex items-center gap-2">
                                        <component v-if="subItem.icon" :is="subItem.icon" class="size-4" />
                                        <span>{{ subItem.title }}</span>
                                    </span>
                                    <Badge
                                        v-if="subItem.badge || subItem.badgeCount"
                                        :variant="getBadgeVariant(subItem.badgeColor)"
                                        class="h-5 min-w-5 px-1.5 text-xs ml-auto me-1"
                                    >
                                        {{ subItem.badge || subItem.badgeCount }}
                                    </Badge>
                                </Link>
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </SidebarMenuItem>

                <!-- Navigation Group with nested items - Collapsible mode when expanded -->
                <Collapsible
                    v-else-if="item.type === 'group' && item.items && item.items.length > 0"
                    as-child
                    :open="openGroups[item.title]"
                    @update:open="(isOpen) => toggleGroup(item.title, isOpen)"
                    class="group/collapsible"
                    :data-animating="shouldAnimate(item.title) ? 'true' : undefined"
                >
                    <SidebarMenuItem>
                        <CollapsibleTrigger as-child>
                            <SidebarMenuButton :tooltip="item.title">
                                <component v-if="item.icon" :is="item.icon" />
                                <span>{{ item.title }}</span>
                                <ChevronRight
                                    class="ms-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90 rtl:-rotate-180 rtl:group-data-[state=open]/collapsible:-rotate-90"
                                />
                            </SidebarMenuButton>
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            <SidebarMenuSub>
                                <SidebarMenuSubItem
                                    v-for="subItem in item.items"
                                    :key="subItem.title"
                                >
                                    <SidebarMenuSubButton
                                        as-child
                                        :is-active="urlIsActive(subItem.url, page.url)"
                                    >
                                        <Link :href="subItem.url" class="flex items-center justify-between w-full">
                                            <span class="flex items-center gap-2">
                                                <component v-if="subItem.icon" :is="subItem.icon" class="size-3.5" />
                                                <span>{{ subItem.title }}</span>
                                            </span>
                                            <Badge
                                                v-if="subItem.badge || subItem.badgeCount"
                                                :variant="getBadgeVariant(subItem.badgeColor)"
                                                class="h-5 min-w-5 px-1.5 text-xs ml-auto me-1"
                                            >
                                                {{ subItem.badge || subItem.badgeCount }}
                                            </Badge>
                                        </Link>
                                    </SidebarMenuSubButton>
                                </SidebarMenuSubItem>
                            </SidebarMenuSub>
                        </CollapsibleContent>
                    </SidebarMenuItem>
                </Collapsible>

                <!-- Regular navigation item -->
                <SidebarMenuItem v-else>
                    <SidebarMenuButton
                        as-child
                        :is-active="isNavItemActive(item)"
                        :tooltip="item.title"
                    >
                        <Link :href="item.url || item.href">
                            <component v-if="item.icon" :is="item.icon" />
                            <span>{{ item.title }}</span>
                        </Link>
                    </SidebarMenuButton>
                    <SidebarMenuBadge v-if="item.badge || item.badgeCount">
                        <Badge
                            :variant="getBadgeVariant(item.badgeColor)"
                            class="h-5 min-w-5 px-1.5 text-xs"
                        >
                            {{ item.badge || item.badgeCount }}
                        </Badge>
                    </SidebarMenuBadge>
                </SidebarMenuItem>
            </template>
        </SidebarMenu>
    </SidebarGroup>
</template>
