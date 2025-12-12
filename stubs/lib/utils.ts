import { InertiaLinkProps } from '@inertiajs/vue3';
import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function urlIsActive(
    urlToCheck: NonNullable<InertiaLinkProps['href']>,
    currentUrl: string,
) {
    const url = toUrl(urlToCheck);
    if (!url || !currentUrl) return false;

    // Extract path from URL (handles both full URLs and paths)
    const extractPath = (u: string) => {
        try {
            // If it's a full URL, extract just the pathname
            if (u.startsWith('http://') || u.startsWith('https://')) {
                const urlObj = new URL(u);
                return urlObj.pathname;
            }
            return u;
        } catch {
            return u;
        }
    };

    // Normalize URLs - remove query strings and trailing slashes
    const normalizeUrl = (u: string) => {
        // First extract the path from full URL
        let normalized = extractPath(u);
        // Remove query string and hash
        normalized = normalized.split('?')[0].split('#')[0];
        // Remove trailing slash (but keep root /)
        if (normalized.length > 1 && normalized.endsWith('/')) {
            normalized = normalized.slice(0, -1);
        }
        return normalized;
    };

    const normalizedUrl = normalizeUrl(url);
    const normalizedCurrentUrl = normalizeUrl(currentUrl);

    // Exact match after normalization
    if (normalizedUrl === normalizedCurrentUrl) return true;

    // For root panel paths like /admin, only do exact matching
    // This prevents /admin from matching /admin/categories, /admin/products, etc.
    const urlParts = normalizedUrl.split('/').filter(Boolean);
    if (urlParts.length <= 1) {
        // Root path or single segment path - only exact match
        return false;
    }

    // Prefix match for nested routes (e.g., /admin/categories matches /admin/categories/1/edit)
    // But don't match if it's just a partial path (e.g., /admin/cat shouldn't match /admin/categories)
    if (normalizedCurrentUrl.startsWith(normalizedUrl) &&
        (normalizedCurrentUrl[normalizedUrl.length] === '/' || normalizedCurrentUrl[normalizedUrl.length] === undefined)) {
        return true;
    }

    return false;
}

export function toUrl(href: NonNullable<InertiaLinkProps['href']>) {
    return typeof href === 'string' ? href : href?.url;
}
