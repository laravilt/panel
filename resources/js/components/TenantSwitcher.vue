<script setup lang="ts">
import { ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Building2, Check, ChevronsUpDown, Plus, Settings } from 'lucide-vue-next';
import { useLocalization } from '@/composables/useLocalization';

const { trans } = useLocalization();

interface Tenant {
    id: string | number;
    name: string;
    slug: string;
    avatar?: string | null;
    is_current: boolean;
}

interface TenancyData {
    current: Tenant | null;
    tenants: Tenant[];
    canRegister: boolean;
    canEditProfile: boolean;
    hasTenantMenu: boolean;
    menuItems: Record<string, any>;
    switchUrl: string;
}

const page = usePage<{
    panel?: {
        id: string;
        path: string;
        brandLogo?: string | null;
        hasTenancy: boolean;
        tenancy?: TenancyData;
    };
}>();

const tenancy = computed(() => page.props?.panel?.tenancy);
const currentTenant = computed(() => tenancy.value?.current);
const tenants = computed(() => tenancy.value?.tenants || []);
const canRegister = computed(() => tenancy.value?.canRegister || false);
const canEditProfile = computed(() => tenancy.value?.canEditProfile || false);
const panelLogo = computed(() => page.props?.panel?.brandLogo);

const isSwitching = ref(false);

const getInitials = (name: string): string => {
    return name
        .split(' ')
        .map((word) => word[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);
};

const panelPath = computed(() => page.props?.panel?.path || '');

const switchTenant = async (tenant: Tenant) => {
    if (tenant.is_current || isSwitching.value) return;

    isSwitching.value = true;

    router.post(
        tenancy.value?.switchUrl || '',
        { tenant_id: tenant.id },
        {
            preserveScroll: true,
            onFinish: () => {
                isSwitching.value = false;
            },
        }
    );
};

const goToSettings = () => {
    router.visit(`/${panelPath.value}/tenant/settings`);
};

const goToCreateTeam = () => {
    router.visit(`/${panelPath.value}/tenant/register`);
};
</script>

<template>
    <SidebarMenu>
        <SidebarMenuItem>
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <SidebarMenuButton
                        size="lg"
                        class="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                    >
                        <Avatar class="h-8 w-8 rounded-md">
                            <AvatarImage v-if="currentTenant?.avatar" :src="currentTenant.avatar" />
                            <AvatarImage v-else-if="panelLogo" :src="panelLogo" />
                            <AvatarFallback class="rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
                                <Building2 v-if="!currentTenant" class="h-4 w-4" />
                                <span v-else>{{ getInitials(currentTenant.name) }}</span>
                            </AvatarFallback>
                        </Avatar>
                        <div class="grid flex-1 text-start text-sm leading-tight">
                            <span class="truncate font-semibold">
                                {{ currentTenant?.name || trans('panel::panel.tenancy.select_tenant') }}
                            </span>
                            <span v-if="tenants.length > 1" class="truncate text-xs text-muted-foreground">
                                {{ trans('panel::panel.tenancy.tenants_count', { count: tenants.length }) }}
                            </span>
                        </div>
                        <ChevronsUpDown class="ml-auto size-4" />
                    </SidebarMenuButton>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    class="w-[--reka-dropdown-menu-trigger-width] min-w-56 rounded-lg"
                    side="bottom"
                    align="start"
                    :side-offset="4"
                >
                    <DropdownMenuLabel class="text-xs text-muted-foreground">
                        {{ trans('panel::panel.tenancy.tenants') }}
                    </DropdownMenuLabel>
                    <DropdownMenuItem
                        v-for="tenant in tenants"
                        :key="tenant.id"
                        class="gap-2 p-2"
                        :class="{ 'bg-accent': tenant.is_current }"
                        @click="switchTenant(tenant)"
                    >
                        <Avatar class="h-6 w-6 rounded-md">
                            <AvatarImage v-if="tenant.avatar" :src="tenant.avatar" />
                            <AvatarImage v-else-if="panelLogo" :src="panelLogo" />
                            <AvatarFallback class="rounded-md text-xs">
                                {{ getInitials(tenant.name) }}
                            </AvatarFallback>
                        </Avatar>
                        <span class="flex-1 truncate">{{ tenant.name }}</span>
                        <Check v-if="tenant.is_current" class="h-4 w-4" />
                    </DropdownMenuItem>

                    <template v-if="canRegister || canEditProfile">
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            v-if="canEditProfile && currentTenant"
                            class="gap-2 p-2 cursor-pointer"
                            @click="goToSettings"
                        >
                            <Settings class="h-4 w-4" />
                            {{ trans('panel::panel.tenancy.tenant_settings') }}
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            v-if="canRegister"
                            class="gap-2 p-2 cursor-pointer"
                            @click="goToCreateTeam"
                        >
                            <Plus class="h-4 w-4" />
                            {{ trans('panel::panel.tenancy.create_tenant') }}
                        </DropdownMenuItem>
                    </template>
                </DropdownMenuContent>
            </DropdownMenu>
        </SidebarMenuItem>
    </SidebarMenu>
</template>
