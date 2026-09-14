import { Link } from '@inertiajs/react';
import { LayoutGrid, type LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { resolveIcon } from '@laravilt/support/lib/icons';
import AppLogoIcon from './AppLogoIcon';
import TenantSwitcher from './TenantSwitcher';

// Props for component mode (when used via Blade bridge)
export interface PanelSidebarProps {
    navigation?: any[];
    panel?: {
        id: string;
        path: string;
        brandName: string;
        brandLogo?: string;
        hasTenancy?: boolean;
        tenancy?: {
            current: any;
            tenants: any[];
            canRegister: boolean;
            canEditProfile: boolean;
            hasTenantMenu: boolean;
            menuItems: Record<string, any>;
            switchUrl: string;
        };
        [key: string]: any;
    };
    user?: {
        name: string;
        email: string;
    };
    collapsible?: boolean;
    variant?: string;
    children?: ReactNode;
}

// Heroicon → lucide mapping (canonical lucide-react names: `Home` → `House`, `BarChart` → `ChartBar`).
const heroiconMap: Record<string, string> = {
    'heroicon-o-home': 'House',
    'heroicon-o-user': 'User',
    'heroicon-o-users': 'Users',
    'heroicon-o-user-group': 'Users',
    'heroicon-o-cog': 'Settings',
    'heroicon-o-chart-bar': 'ChartBar',
    'heroicon-o-document-text': 'FileText',
    'heroicon-o-folder': 'Folder',
    'heroicon-o-shopping-cart': 'ShoppingCart',
};

// Get Lucide icon component from icon name
const getIconComponent = (iconName: string | null | undefined): LucideIcon => {
    if (!iconName) return LayoutGrid;

    // If it starts with 'heroicon-o-', map it to Lucide
    if (iconName.startsWith('heroicon-o-')) {
        const lucideName = heroiconMap[iconName] || 'LayoutGrid';
        return resolveIcon(lucideName) ?? LayoutGrid;
    }

    // Try to use it as a Lucide icon name directly
    return resolveIcon(iconName) ?? LayoutGrid;
};

export default function PanelSidebar({ navigation, panel: panelProp, children }: PanelSidebarProps) {
    // Support prop-based panel data
    const panel: Record<string, any> = panelProp || {};

    // Use component prop navigation if available
    const nav: any[] = navigation || panel.navigation || [];

    const panelNavigation: any[] = nav.map((item: any) => ({
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

    const dashboardHref = `/${panel.path || 'admin'}`;
    const hasTenancy = panel.hasTenancy || false;

    return (
        <>
            <Sidebar collapsible="icon" variant="inset">
                <SidebarHeader>
                    {hasTenancy ? (
                        // Tenant Switcher (when tenancy is enabled)
                        <TenantSwitcher />
                    ) : (
                        // Default Brand Header (when tenancy is disabled)
                        <SidebarMenu>
                            <SidebarMenuItem>
                                <SidebarMenuButton size="lg" asChild>
                                    <Link href={dashboardHref}>
                                        {/* Brand Logo */}
                                        <div className="flex aspect-square size-8 items-center justify-center">
                                            {panel.brandLogo ? (
                                                <img
                                                    src={panel.brandLogo}
                                                    alt={panel.brandName || 'Logo'}
                                                    className="size-7 object-contain"
                                                />
                                            ) : (
                                                <AppLogoIcon className="size-7" />
                                            )}
                                        </div>
                                        {/* Brand Name */}
                                        <div className="ms-1 grid flex-1 text-sm">
                                            <span className="mb-0.5 truncate leading-tight font-semibold text-start rtl:text-right">
                                                {panel.brandName || 'Admin Panel'}
                                            </span>
                                        </div>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        </SidebarMenu>
                    )}
                </SidebarHeader>

                <SidebarContent>
                    <NavMain items={panelNavigation} />
                </SidebarContent>

                <SidebarFooter>
                    <NavUser />
                </SidebarFooter>
            </Sidebar>
            {children}
        </>
    );
}
