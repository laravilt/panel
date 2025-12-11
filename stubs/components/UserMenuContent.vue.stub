<script setup lang="ts">
import UserInfo from '@/components/UserInfo.vue';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuSub,
    DropdownMenuSubTrigger,
    DropdownMenuSubContent,
} from '@/components/ui/dropdown-menu';
import type { User, MenuItem, Panel } from '@/types';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import * as LucideIcons from 'lucide-vue-next';
import { Monitor, Moon, Sun, Check, Palette } from 'lucide-vue-next';
import { useAppearance } from '@/composables/useAppearance';
import { useLocalization } from '@laravilt/support/composables';

const { trans } = useLocalization();

interface Props {
    user: User;
}

defineProps<Props>();

const page = usePage<{ panel?: Panel }>();
const userMenuItems = computed(() => {
    return page.props.panel?.userMenu || [];
});

const { appearance, updateAppearance } = useAppearance();

const appearanceOptions = computed(() => [
    { value: 'light' as const, Icon: Sun, label: trans('laravilt-panel::panel.appearance.light') },
    { value: 'dark' as const, Icon: Moon, label: trans('laravilt-panel::panel.appearance.dark') },
    { value: 'system' as const, Icon: Monitor, label: trans('laravilt-panel::panel.appearance.system') },
]);

// Map Heroicons names to Lucide equivalents
const iconNameMap: Record<string, string> = {
    'cog-6-tooth': 'Settings',
    'arrow-right-on-rectangle': 'LogOut',
    'user': 'User',
    'shield-check': 'ShieldCheck',
};

const getIconComponent = (iconName: string | null) => {
    if (!iconName) return null;

    // First check if there's a direct mapping
    const mappedName = iconNameMap[iconName];
    if (mappedName && (LucideIcons as any)[mappedName]) {
        return (LucideIcons as any)[mappedName];
    }

    // Otherwise try to convert kebab-case to PascalCase
    const pascalCase = iconName
        .split('-')
        .map(word => word.charAt(0).toUpperCase() + word.slice(1))
        .join('');

    return (LucideIcons as any)[pascalCase] || null;
};

const handleMenuItemClick = (item: MenuItem) => {
    if (!item.url) return;

    if (item.method === 'post') {
        router.post(item.url, {}, {
            onFinish: () => router.flushAll()
        });
    }
};
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm rtl:flex-row-reverse">
            <UserInfo :user="user" :show-email="true" />
        </div>
    </DropdownMenuLabel>
    <DropdownMenuSeparator />

    <!-- Appearance submenu -->
    <DropdownMenuGroup>
        <DropdownMenuSub>
            <DropdownMenuSubTrigger class="rtl:flex-row-reverse">
                <Palette class="h-4 w-4" />
                {{ trans('laravilt-panel::panel.appearance.title') }}
            </DropdownMenuSubTrigger>
            <DropdownMenuSubContent>
                <DropdownMenuItem
                    v-for="option in appearanceOptions"
                    :key="option.value"
                    @click="updateAppearance(option.value)"
                    class="gap-2 rtl:flex-row-reverse"
                >
                    <component :is="option.Icon" class="h-4 w-4" />
                    {{ option.label }}
                    <Check
                        v-if="appearance === option.value"
                        class="ms-auto h-4 w-4"
                    />
                </DropdownMenuItem>
            </DropdownMenuSubContent>
        </DropdownMenuSub>
    </DropdownMenuGroup>
    <DropdownMenuSeparator />

    <template v-for="(item, index) in userMenuItems" :key="index">
        <DropdownMenuGroup v-if="item.type === 'item'">
            <DropdownMenuItem
                v-if="item.method === 'post'"
                as-child
                class="rtl:flex-row-reverse"
            >
                <button
                    class="w-full"
                    @click="handleMenuItemClick(item)"
                    :data-test="item.title ? item.title.toLowerCase().replace(/\s+/g, '-') + '-button' : ''"
                >
                    <component
                        v-if="getIconComponent(item.icon)"
                        :is="getIconComponent(item.icon)"
                        class="h-4 w-4"
                    />
                    {{ item.translationKey ? trans(item.translationKey) : item.title }}
                </button>
            </DropdownMenuItem>

            <DropdownMenuItem
                v-else
                :as-child="true"
                class="rtl:flex-row-reverse"
            >
                <Link
                    class="block w-full"
                    :href="item.url || '#'"
                    prefetch
                    as="button"
                >
                    <component
                        v-if="getIconComponent(item.icon)"
                        :is="getIconComponent(item.icon)"
                        class="h-4 w-4"
                    />
                    {{ item.translationKey ? trans(item.translationKey) : item.title }}
                </Link>
            </DropdownMenuItem>
        </DropdownMenuGroup>

        <DropdownMenuSeparator v-if="index < userMenuItems.length - 1" />
    </template>
</template>
