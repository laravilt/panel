import { router, usePage } from '@inertiajs/react';
import { Building2, Check, ChevronsUpDown, Plus, Settings } from 'lucide-react';
import { useRef } from 'react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { cn } from '@/lib/utils';
import { useLocalization } from '@laravilt/support/composables/useLocalization';

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

interface PanelTenancyData {
    id: string;
    path: string;
    brandLogo?: string | null;
    hasTenancy: boolean;
    tenancy?: TenancyData;
}

const getInitials = (name: string): string => {
    return name
        .split(' ')
        .map((word) => word[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);
};

export default function TenantSwitcher() {
    const { trans } = useLocalization();
    const page = usePage();
    const panel = (page.props as any)?.panel as PanelTenancyData | undefined;

    const tenancy = panel?.tenancy;
    const currentTenant = tenancy?.current;
    const tenants = tenancy?.tenants || [];
    const canRegister = tenancy?.canRegister || false;
    const canEditProfile = tenancy?.canEditProfile || false;
    const panelLogo = panel?.brandLogo;
    const isMultiDatabase = tenancy?.isMultiDatabase || false;

    const isSwitching = useRef(false);

    const panelPath = panel?.path || '';

    const switchTenant = async (tenant: Tenant) => {
        if (tenant.is_current || isSwitching.current) return;

        isSwitching.current = true;

        // For multi-database tenancy, redirect to the tenant's subdomain URL
        if (isMultiDatabase && tenant.url) {
            window.location.href = tenant.url;
            return;
        }

        // For single-database tenancy, use POST to switch tenant in session
        router.post(
            tenancy?.switchUrl || '',
            { tenant_id: tenant.id },
            {
                preserveScroll: true,
                onFinish: () => {
                    isSwitching.current = false;
                },
            },
        );
    };

    const goToSettings = () => {
        router.visit(`/${panelPath}/tenant-settings`);
    };

    const goToCreateTeam = () => {
        // For multi-database tenancy, registration should be on the main domain
        if (isMultiDatabase) {
            const baseDomain = panel?.tenancy?.baseDomain;
            if (baseDomain) {
                const scheme = window.location.protocol;
                window.location.href = `${scheme}//${baseDomain}/${panelPath}/tenant/register`;
                return;
            }
        }
        router.visit(`/${panelPath}/tenant/register`);
    };

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <SidebarMenuButton
                            size="lg"
                            className="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                        >
                            <div className="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
                                {currentTenant?.avatar ? (
                                    <img
                                        src={currentTenant.avatar}
                                        alt={currentTenant?.name}
                                        className="h-full w-full object-cover"
                                    />
                                ) : !currentTenant ? (
                                    panelLogo ? (
                                        <img src={panelLogo} className="h-full w-full p-1.5 object-contain" />
                                    ) : (
                                        <Building2 className="h-full w-full p-2" />
                                    )
                                ) : panelLogo ? (
                                    // Current tenant exists but has no avatar
                                    <img src={panelLogo} alt={currentTenant.name} className="h-full w-full p-1.5 object-contain" />
                                ) : (
                                    <span className="flex h-full w-full items-center justify-center text-sm font-medium">
                                        {getInitials(currentTenant.name)}
                                    </span>
                                )}
                            </div>
                            <div className="grid flex-1 text-start text-sm leading-tight">
                                <span className="truncate font-semibold">
                                    {currentTenant?.name || trans('panel::panel.tenancy.select_tenant')}
                                </span>
                                {tenants.length > 1 && (
                                    <span className="truncate text-xs text-muted-foreground">
                                        {trans('panel::panel.tenancy.tenants_count', { count: tenants.length })}
                                    </span>
                                )}
                            </div>
                            <ChevronsUpDown className="ms-auto size-4" />
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        className="w-[--reka-dropdown-menu-trigger-width] min-w-56 rounded-lg"
                        side="bottom"
                        align="end"
                        sideOffset={4}
                    >
                        <DropdownMenuLabel className="text-xs text-muted-foreground">
                            {trans('panel::panel.tenancy.tenants')}
                        </DropdownMenuLabel>
                        {tenants.map((tenant) => (
                            <DropdownMenuItem
                                key={tenant.id}
                                className={cn('gap-2 p-2', { 'bg-accent': tenant.is_current })}
                                onClick={() => switchTenant(tenant)}
                            >
                                <div className="relative flex h-6 w-6 shrink-0 overflow-hidden rounded-md bg-sidebar-primary">
                                    {tenant.avatar ? (
                                        <img src={tenant.avatar} alt={tenant.name} className="h-full w-full object-cover" />
                                    ) : panelLogo ? (
                                        <img src={panelLogo} alt={tenant.name} className="h-full w-full p-1 object-contain" />
                                    ) : (
                                        <span className="flex h-full w-full items-center justify-center text-xs text-sidebar-primary-foreground">
                                            {getInitials(tenant.name)}
                                        </span>
                                    )}
                                </div>
                                <span className="flex-1 truncate">{tenant.name}</span>
                                {tenant.is_current && <Check className="h-4 w-4" />}
                            </DropdownMenuItem>
                        ))}

                        {(canRegister || canEditProfile) && (
                            <>
                                <DropdownMenuSeparator />
                                {canEditProfile && currentTenant && (
                                    <DropdownMenuItem className="gap-2 p-2 cursor-pointer" onClick={goToSettings}>
                                        <Settings className="h-4 w-4" />
                                        {trans('panel::panel.tenancy.tenant_settings')}
                                    </DropdownMenuItem>
                                )}
                                {canRegister && (
                                    <DropdownMenuItem className="gap-2 p-2 cursor-pointer" onClick={goToCreateTeam}>
                                        <Plus className="h-4 w-4" />
                                        {trans('panel::panel.tenancy.create_tenant')}
                                    </DropdownMenuItem>
                                )}
                            </>
                        )}
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
