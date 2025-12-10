<script setup lang="ts">
import { computed } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Button } from '@/components/ui/button';
import { Globe, Check } from 'lucide-vue-next';
import { useLocalization } from '@/composables/useLocalization';

interface Locale {
    value: string;
    label: string;
    dir: 'ltr' | 'rtl';
    flag: string;
}

const { trans } = useLocalization();

const page = usePage<{
    panel?: {
        id: string;
        path: string;
        availableLocales: Locale[];
        currentLocale: string;
    };
}>();

const availableLocales = computed(() => page.props.panel?.availableLocales || []);
const currentLocale = computed(() => page.props.panel?.currentLocale || 'en');
const panelPath = computed(() => page.props.panel?.path || '');
const panelId = computed(() => page.props.panel?.id || 'admin');

const currentLocaleData = computed(() => {
    return availableLocales.value.find(l => l.value === currentLocale.value) || {
        value: 'en',
        label: 'English',
        dir: 'ltr',
        flag: 'us'
    };
});

const getFlagUrl = (flag: string) => {
    return `https://flagcdn.com/w20/${flag}.png`;
};

const switchLocale = (locale: string) => {
    if (locale === currentLocale.value) return;

    // Build the URL using panel path
    const url = `/${panelPath.value}/locale`;

    router.post(url, {
        locale,
    }, {
        preserveScroll: true,
        preserveState: false,
        onSuccess: () => {
            // Use Inertia's reload instead of window.location.reload()
            // This provides a smoother transition
            router.reload({ only: [] });
        },
    });
};
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button variant="ghost" size="icon" class="relative">
                <img
                    :src="getFlagUrl(currentLocaleData.flag)"
                    :alt="currentLocaleData.label"
                    class="h-5 w-5 rounded-sm object-cover"
                />
                <span class="sr-only">{{ trans('laravilt-panel::panel.language.switch') }}</span>
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-48">
            <DropdownMenuItem
                v-for="locale in availableLocales"
                :key="locale.value"
                class="flex items-center gap-3 cursor-pointer"
                @click="switchLocale(locale.value)"
            >
                <img
                    :src="getFlagUrl(locale.flag)"
                    :alt="locale.label"
                    class="h-4 w-4 rounded-sm object-cover"
                />
                <span :class="{ 'flex-1': currentLocaleData.dir === 'ltr' }" class="text-start">{{ locale.label }}</span>
                <Check
                    v-if="locale.value === currentLocale"
                    class="h-4 w-4 text-primary rtl:me-auto"
                />
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
