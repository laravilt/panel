import { Skeleton } from '@/components/ui/skeleton';

export default function SettingsPageSkeleton() {
    return (
        <div className="max-w-2xl space-y-6">
            {/* Page Header Skeleton */}
            <header className="space-y-2">
                <Skeleton className="h-6 w-48" />
                <Skeleton className="h-4 w-80" />
            </header>

            {/* Content Skeleton */}
            <div className="space-y-6">
                {/* Card/Section Skeleton */}
                <div className="flex items-start gap-3">
                    <Skeleton className="h-10 w-10 rounded-lg shrink-0" />
                    <div className="flex-1 space-y-2">
                        <Skeleton className="h-5 w-32" />
                        <Skeleton className="h-4 w-full max-w-md" />
                    </div>
                </div>

                {/* Form Fields Skeleton */}
                <div className="space-y-4">
                    <div className="space-y-2">
                        <Skeleton className="h-4 w-24" />
                        <Skeleton className="h-10 w-full" />
                    </div>
                    <div className="space-y-2">
                        <Skeleton className="h-4 w-24" />
                        <Skeleton className="h-10 w-full" />
                    </div>
                </div>

                {/* Action Button Skeleton */}
                <Skeleton className="h-10 w-32" />
            </div>
        </div>
    );
}
