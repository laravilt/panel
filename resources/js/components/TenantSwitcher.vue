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
import { Building2, Check, ChevronsUpDown, Plus, Settings } from 'lucide-vue-next';
import { useLocalization } from '@/composables/useLocalization';

const { trans } = useLocalization();

interface Tenant {
    id: string | number;
    name: string;
    slug: string;
    avatar?: string | null;
    url?: string | null;
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
    isMultiDatabase: boolean;
    baseDomain?: string | null;
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
const isMultiDatabase = computed(() => tenancy.value?.isMultiDatabase || false);

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

    // For multi-database tenancy, redirect to the tenant's subdomain URL
    if (isMultiDatabase.value && tenant.url) {
        window.location.href = tenant.url;
        return;
    }

    // For single-database tenancy, use POST to switch tenant in session
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
    // For multi-database tenancy, registration should be on the main domain
    if (isMultiDatabase.value) {
        const baseDomain = page.props?.panel?.tenancy?.baseDomain;
        if (baseDomain) {
            const scheme = window.location.protocol;
            window.location.href = `${scheme}//${baseDomain}/${panelPath.value}/tenant/register`;
            return;
        }
    }
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
                        <div class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
                            <img
                                v-if="currentTenant?.avatar"
                                :src="currentTenant.avatar"
                                :alt="currentTenant?.name"
                                class="h-full w-full object-cover"
                            />
                            <template v-else-if="!currentTenant">
                                <img v-if="panelLogo" :src="panelLogo" class="h-full w-full p-1.5 object-contain" />
                                <Building2 v-else class="h-full w-full p-2" />
                            </template>
                            <template v-else>
                                <!-- Current tenant exists but has no avatar -->
                                <img v-if="panelLogo" :src="panelLogo" :alt="currentTenant.name" class="h-full w-full p-1.5 object-contain" />
                                <span v-else class="flex h-full w-full items-center justify-center text-sm font-medium">{{ getInitials(currentTenant.name) }}</span>
                            </template>
                        </div>
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
                        <div class="relative flex h-6 w-6 shrink-0 overflow-hidden rounded-md bg-sidebar-primary">
                            <img
                                v-if="tenant.avatar"
                                :src="tenant.avatar"
                                :alt="tenant.name"
                                class="h-full w-full object-cover"
                            />
                            <template v-else>
                                <img
                                    v-if="panelLogo"
                                    :src="panelLogo"
                                    :alt="tenant.name"
                                    class="h-full w-full p-1 object-contain"
                                />
                                <span
                                    v-else
                                    class="flex h-full w-full items-center justify-center text-xs text-sidebar-primary-foreground"
                                >
                                    {{ getInitials(tenant.name) }}
                                </span>
                            </template>
                        </div>
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
