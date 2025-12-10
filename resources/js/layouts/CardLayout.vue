<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { NotificationContainer } from '@laravilt/notifications/app.ts';
import { usePage } from '@inertiajs/vue3';
import { computed, onMounted, watchEffect } from 'vue';

interface Props {
    title?: string;
    description?: string;
}

const props = defineProps<Props>();
const page = usePage();

// Get the panel or home URL
const homeUrl = computed(() => {
    const panelId = (page.props as any).panelId;
    return panelId ? `/${panelId}` : '/';
});

// Get panel data
const panel = computed(() => (page.props as any).panel);
const brandLogo = computed(() => panel.value?.brandLogo);
const brandName = computed(() => panel.value?.brandName);
const font = computed(() => panel.value?.font);

// Apply font styles when component mounts
watchEffect(() => {
    if (font.value && typeof document !== 'undefined') {
        // Load Google Font if URL is provided
        if (font.value.url) {
            const existingLink = document.querySelector(`link[href="${font.value.url}"]`);
            if (!existingLink) {
                const link = document.createElement('link');
                link.rel = 'stylesheet';
                link.href = font.value.url;
                document.head.appendChild(link);
            }
        }

        // Apply font family to body
        if (font.value.family) {
            document.body.style.fontFamily = `"${font.value.family}", sans-serif`;
        }
    }
});
</script>

<template>
    <div
        class="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10"
        :style="font?.family ? { fontFamily: `'${font.family}', sans-serif` } : {}"
    >
        <div class="w-full max-w-sm">
            <div class="flex flex-col gap-6">
                <!-- Logo -->
                <div class="flex flex-col items-center gap-3">
                    <a
                        :href="homeUrl"
                        class="flex flex-col items-center gap-2 font-medium"
                    >
                        <div
                            class="flex items-center justify-center"
                        >
                            <!-- Use panel brand logo if available -->
                            <img
                                v-if="brandLogo"
                                :src="brandLogo"
                                :alt="brandName || title"
                                class="h-9 w-auto max-w-[200px] object-contain"
                            />
                            <!-- Fallback to AppLogoIcon -->
                            <AppLogoIcon
                                v-else
                                class="size-9 fill-current text-[var(--foreground)] dark:text-white"
                            />
                        </div>
                        <span class="sr-only">{{ brandName || title }}</span>
                    </a>

                    <!-- Title and Description -->
                    <div class="space-y-1 text-center">
                        <h1 class="text-xl font-medium">{{ title }}</h1>
                        <p
                            v-if="description"
                            class="text-center text-sm text-muted-foreground"
                        >
                            {{ description }}
                        </p>
                    </div>
                </div>

                <!-- Content -->
                <slot />
            </div>
        </div>
        <NotificationContainer />
    </div>
</template>
