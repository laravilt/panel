<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useForm, Head } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Building2, Globe, AlertCircle } from 'lucide-vue-next';
import { useLocalization } from '@/composables/useLocalization';
import InputError from '@/components/InputError.vue';

const { trans } = useLocalization();

const props = defineProps<{
    panel: {
        id: string;
        path: string;
    };
    baseDomain: string;
    reservedSubdomains: string[];
    isMultiDatabase: boolean;
}>();

const form = useForm({
    name: '',
    subdomain: '',
    description: '',
});

// Auto-generate subdomain from name
const autoGenerateSubdomain = ref(true);

watch(() => form.name, (newName) => {
    if (autoGenerateSubdomain.value && newName) {
        form.subdomain = newName
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-|-$/g, '')
            .substring(0, 63);
    }
});

// When user manually edits subdomain, stop auto-generation
const onSubdomainInput = () => {
    autoGenerateSubdomain.value = false;
};

// Validate subdomain format
const subdomainError = computed(() => {
    if (!form.subdomain) return null;

    if (!/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/.test(form.subdomain)) {
        return trans('panel::panel.tenancy.subdomain_invalid');
    }

    if (props.reservedSubdomains.includes(form.subdomain)) {
        return trans('panel::panel.tenancy.subdomain_reserved');
    }

    return null;
});

// Full domain preview
const fullDomain = computed(() => {
    if (!form.subdomain) return `your-team.${props.baseDomain}`;
    return `${form.subdomain}.${props.baseDomain}`;
});

const submit = () => {
    if (subdomainError.value) return;
    form.post(`/${props.panel.path}/tenant/register`);
};
</script>

<template>
    <Head :title="trans('panel::panel.tenancy.create_tenant')" />

    <div class="min-h-screen flex items-center justify-center bg-background p-4">
        <Card class="w-full max-w-lg">
            <CardHeader class="text-center">
                <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-primary/10">
                    <Building2 class="h-6 w-6 text-primary" />
                </div>
                <CardTitle class="text-2xl">{{ trans('panel::panel.tenancy.create_tenant') }}</CardTitle>
                <CardDescription>
                    {{ trans('panel::panel.tenancy.create_tenant_description') }}
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form @submit.prevent="submit" class="space-y-5">
                    <!-- Team Name -->
                    <div class="space-y-2">
                        <Label for="name">{{ trans('panel::panel.tenancy.team_name') }}</Label>
                        <Input
                            id="name"
                            v-model="form.name"
                            type="text"
                            :placeholder="trans('panel::panel.tenancy.team_name_placeholder')"
                            required
                            autofocus
                        />
                        <InputError :message="form.errors.name" />
                    </div>

                    <!-- Subdomain -->
                    <div class="space-y-2">
                        <Label for="subdomain">{{ trans('panel::panel.tenancy.subdomain') }}</Label>
                        <div class="flex items-center gap-1">
                            <div class="relative flex-1">
                                <Input
                                    id="subdomain"
                                    v-model="form.subdomain"
                                    type="text"
                                    :placeholder="trans('panel::panel.tenancy.subdomain_placeholder')"
                                    class="pr-2"
                                    required
                                    @input="onSubdomainInput"
                                />
                            </div>
                            <span class="text-sm text-muted-foreground whitespace-nowrap">.{{ baseDomain }}</span>
                        </div>

                        <!-- Domain Preview -->
                        <div class="flex items-center gap-2 p-2 rounded-md bg-muted/50">
                            <Globe class="h-4 w-4 text-muted-foreground shrink-0" />
                            <span class="text-sm text-muted-foreground truncate">
                                https://{{ fullDomain }}
                            </span>
                        </div>

                        <!-- Validation Error -->
                        <div v-if="subdomainError" class="flex items-center gap-2 text-destructive text-sm">
                            <AlertCircle class="h-4 w-4 shrink-0" />
                            <span>{{ subdomainError }}</span>
                        </div>

                        <InputError :message="form.errors.subdomain" />
                    </div>

                    <!-- Description (optional) -->
                    <div class="space-y-2">
                        <Label for="description">
                            {{ trans('panel::panel.tenancy.team_description') }}
                            <span class="text-muted-foreground font-normal">({{ trans('panel::panel.common.optional') }})</span>
                        </Label>
                        <Textarea
                            id="description"
                            v-model="form.description"
                            :placeholder="trans('panel::panel.tenancy.team_description_placeholder')"
                            rows="3"
                        />
                        <InputError :message="form.errors.description" />
                    </div>

                    <!-- Info Box -->
                    <div class="rounded-lg border bg-muted/30 p-4 text-sm space-y-2">
                        <p class="font-medium">{{ trans('panel::panel.tenancy.what_happens_next') }}</p>
                        <ul class="list-disc list-inside text-muted-foreground space-y-1">
                            <li>{{ trans('panel::panel.tenancy.provision_database') }}</li>
                            <li>{{ trans('panel::panel.tenancy.provision_subdomain') }}</li>
                            <li>{{ trans('panel::panel.tenancy.provision_redirect') }}</li>
                        </ul>
                    </div>

                    <Button
                        type="submit"
                        class="w-full"
                        :disabled="form.processing || !!subdomainError"
                    >
                        <span v-if="form.processing">{{ trans('panel::panel.common.loading') }}</span>
                        <span v-else>{{ trans('panel::panel.tenancy.create_tenant') }}</span>
                    </Button>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
