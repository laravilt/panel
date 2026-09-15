import type { InertiaLinkProps } from '@inertiajs/react';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(url: NonNullable<InertiaLinkProps['href']>): string {
    return typeof url === 'string' ? url : url.url;
}

export function urlIsActive(
    urlToCheck: NonNullable<InertiaLinkProps['href']>,
    currentUrl: string,
): boolean {
    const url = toUrl(urlToCheck);

    if (!url || !currentUrl) {
        return false;
    }

    // Extract path from URL (handles both full URLs and paths)
    const extractPath = (u: string): string => {
        try {
            // If it's a full URL, extract just the pathname
            if (u.startsWith('http://') || u.startsWith('https://')) {
                return new URL(u).pathname;
            }

            return u;
        } catch {
            return u;
        }
    };

    // Normalize URLs - remove query strings and trailing slashes
    const normalizeUrl = (u: string): string => {
        let normalized = extractPath(u);
        normalized = normalized.split('?')[0].split('#')[0];

        if (normalized.length > 1 && normalized.endsWith('/')) {
            normalized = normalized.slice(0, -1);
        }

        return normalized;
    };

    const normalizedUrl = normalizeUrl(url);
    const normalizedCurrentUrl = normalizeUrl(currentUrl);

    // Exact match after normalization
    if (normalizedUrl === normalizedCurrentUrl) {
        return true;
    }

    // For root panel paths like /admin, only do exact matching
    // This prevents /admin from matching /admin/categories, /admin/products, etc.
    const urlParts = normalizedUrl.split('/').filter(Boolean);

    if (urlParts.length <= 1) {
        return false;
    }

    // Prefix match for nested routes (e.g., /admin/categories matches /admin/categories/1/edit)
    // But don't match partial segments (e.g., /admin/cat shouldn't match /admin/categories)
    if (
        normalizedCurrentUrl.startsWith(normalizedUrl) &&
        (normalizedCurrentUrl[normalizedUrl.length] === '/' ||
            normalizedCurrentUrl[normalizedUrl.length] === undefined)
    ) {
        return true;
    }

    return false;
}
