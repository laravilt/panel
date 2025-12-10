<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';
</script>

<template>
    <div class="max-w-2xl space-y-6">
        <!-- Page Header Skeleton -->
        <header class="space-y-2">
            <Skeleton class="h-6 w-48" />
            <Skeleton class="h-4 w-80" />
        </header>

        <!-- Content Skeleton -->
        <div class="space-y-6">
            <!-- Card/Section Skeleton -->
            <div class="flex items-start gap-3">
                <Skeleton class="h-10 w-10 rounded-lg shrink-0" />
                <div class="flex-1 space-y-2">
                    <Skeleton class="h-5 w-32" />
                    <Skeleton class="h-4 w-full max-w-md" />
                </div>
            </div>

            <!-- Form Fields Skeleton -->
            <div class="space-y-4">
                <div class="space-y-2">
                    <Skeleton class="h-4 w-24" />
                    <Skeleton class="h-10 w-full" />
                </div>
                <div class="space-y-2">
                    <Skeleton class="h-4 w-24" />
                    <Skeleton class="h-10 w-full" />
                </div>
            </div>

            <!-- Action Button Skeleton -->
            <Skeleton class="h-10 w-32" />
        </div>
    </div>
</template>
