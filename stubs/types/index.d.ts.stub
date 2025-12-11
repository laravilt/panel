import { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from 'lucide-vue-next';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    isActive?: boolean;
}

export type AppPageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    sidebarOpen: boolean;
};

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    locale: string;
    timezone: string;
    created_at: string;
    updated_at: string;
}

export interface MenuItem {
    type: 'item' | 'separator';
    title?: string;
    url?: string;
    icon?: string;
    method?: 'get' | 'post';
    translationKey?: string;
}

export interface Panel {
    id: string;
    path: string;
    brandName?: string;
    brandLogo?: string;
    navigation?: NavItem[];
    userMenu?: MenuItem[];
    hasDatabaseNotifications?: boolean;
    databaseNotificationsPolling?: string;
    availableLocales?: { value: string; label: string; dir: string; flag: string }[];
    currentLocale?: string;
    hasDarkMode?: boolean;
    hasGlobalSearch?: boolean;
    globalSearchEndpoint?: string;
    globalSearchConfig?: any;
    hasAI?: boolean;
    aiConfig?: any;
    user?: User;
    auth?: {
        hasProfile: boolean;
        hasLogin: boolean;
        hasRegistration: boolean;
        hasPasswordReset: boolean;
        hasEmailVerification: boolean;
        hasOtp: boolean;
    };
}

export type BreadcrumbItemType = BreadcrumbItem;
