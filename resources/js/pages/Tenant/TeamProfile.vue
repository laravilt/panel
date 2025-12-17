<script setup lang="ts">
import { ref, computed } from 'vue';
import { useForm, Head, router, usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Trash2, Upload, X } from 'lucide-vue-next';
import { useLocalization } from '@laravilt/support/composables';
import InputError from '@/components/InputError.vue';
import SettingsLayout from '@laravilt/panel/layouts/SettingsLayout.vue';

const { trans } = useLocalization();

interface NavigationItem {
    title: string;
    href: string;
    icon?: string;
    active?: boolean;
}

interface Props {
    page: {
        heading: string;
        subheading?: string | null;
    };
    panelId: string;
    team: {
        id: number;
        name: string;
        slug: string;
        description?: string | null;
        avatar?: string | null;
        owner_id: number | null;
        settings?: {
            show_unassigned_records?: boolean;
        };
    };
    isOwner: boolean;
    hasSettings: boolean;
    permissions: {
        canUpdateTeam: boolean;
        canDeleteTeam?: boolean;
    };
    routes?: {
        deleteTeam?: string;
    };
    clusterNavigation?: NavigationItem[];
    clusterTitle?: string;
    clusterDescription?: string;
}

const props = defineProps<Props>();

const page = usePage<{
    panel?: {
        brandLogo?: string | null;
    };
}>();

const panelLogo = computed(() => page.props?.panel?.brandLogo);

// Team Profile Form
const profileForm = useForm({
    name: props.team.name,
    description: props.team.description || '',
    avatar: null as File | null,
    remove_avatar: false,
    show_unassigned_records: props.team.settings?.show_unassigned_records ?? false,
});

// Separate ref for switch to ensure proper binding
const showUnassignedRecords = ref(props.team.settings?.show_unassigned_records ?? false);

// Avatar preview
const avatarPreview = ref<string | null>(props.team.avatar || null);
const avatarInput = ref<HTMLInputElement | null>(null);

const getInitials = (name: string): string => {
    return name
        .split(' ')
        .map((word) => word[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);
};

const selectAvatar = () => {
    avatarInput.value?.click();
};

const handleAvatarChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];

    if (file) {
        profileForm.avatar = file;
        profileForm.remove_avatar = false;

        // Create preview
        const reader = new FileReader();
        reader.onload = (e) => {
            avatarPreview.value = e.target?.result as string;
        };
        reader.readAsDataURL(file);
    }
};

const removeAvatar = () => {
    profileForm.avatar = null;
    profileForm.remove_avatar = true;
    avatarPreview.value = null;
    if (avatarInput.value) {
        avatarInput.value.value = '';
    }
};

const updateTeamProfile = () => {
    // Sync the switch value to the form before submitting
    profileForm.show_unassigned_records = showUnassignedRecords.value;

    profileForm.post(`/${props.panelId}/tenant/settings/profile`, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: (page) => {
            // Update form with fresh data from server response
            const team = page.props.team as Props['team'];
            if (team) {
                profileForm.defaults({
                    name: team.name,
                    description: team.description || '',
                    avatar: null,
                    remove_avatar: false,
                    show_unassigned_records: team.settings?.show_unassigned_records ?? false,
                });
                profileForm.reset();
                showUnassignedRecords.value = team.settings?.show_unassigned_records ?? false;
                avatarPreview.value = team.avatar || null;
            }
        },
    });
};

// Delete Team
const showDeleteDialog = ref(false);
const isDeleting = ref(false);

const deleteTeam = () => {
    if (!props.routes?.deleteTeam) return;

    isDeleting.value = true;
    router.delete(props.routes.deleteTeam, {
        onSuccess: () => {
            showDeleteDialog.value = false;
        },
        onFinish: () => {
            isDeleting.value = false;
        },
    });
};

const layoutProps = {
    navigation: props.clusterNavigation,
    title: props.clusterTitle,
    description: props.clusterDescription,
};
</script>

<template>
    <Head :title="page.heading" />

    <SettingsLayout v-bind="layoutProps">
        <section class="max-w-xl space-y-12">
            <!-- Team Profile Section -->
            <div class="flex flex-col space-y-6">
                <header>
                    <h3 class="mb-0.5 text-base font-medium">
                        {{ page.heading }}
                    </h3>
                    <p v-if="page.subheading" class="text-sm text-muted-foreground">
                        {{ page.subheading }}
                    </p>
                </header>

                <form @submit.prevent="updateTeamProfile" class="space-y-6">
                    <!-- Avatar -->
                    <div class="space-y-2">
                        <Label>{{ trans('panel::panel.tenancy.settings.team_avatar') }}</Label>
                        <div class="flex items-center gap-4">
                            <Avatar class="h-20 w-20 rounded-lg">
                                <AvatarImage v-if="avatarPreview" :src="avatarPreview" />
                                <AvatarImage v-else-if="panelLogo" :src="panelLogo" />
                                <AvatarFallback class="rounded-lg text-lg">
                                    {{ getInitials(profileForm.name || team.name) }}
                                </AvatarFallback>
                            </Avatar>
                            <div class="flex flex-col gap-2">
                                <input
                                    ref="avatarInput"
                                    type="file"
                                    accept="image/*"
                                    class="hidden"
                                    @change="handleAvatarChange"
                                    :disabled="!permissions.canUpdateTeam"
                                />
                                <div class="flex gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        @click="selectAvatar"
                                        :disabled="!permissions.canUpdateTeam"
                                    >
                                        <Upload class="h-4 w-4 mr-2" />
                                        {{ trans('panel::panel.tenancy.settings.upload_avatar') }}
                                    </Button>
                                    <Button
                                        v-if="avatarPreview"
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        @click="removeAvatar"
                                        :disabled="!permissions.canUpdateTeam"
                                    >
                                        <X class="h-4 w-4 mr-2" />
                                        {{ trans('panel::panel.tenancy.settings.remove_avatar') }}
                                    </Button>
                                </div>
                                <p class="text-xs text-muted-foreground">
                                    {{ trans('panel::panel.tenancy.settings.avatar_hint') }}
                                </p>
                            </div>
                        </div>
                        <InputError :message="profileForm.errors.avatar" />
                    </div>

                    <!-- Team Name -->
                    <div class="space-y-2">
                        <Label for="name">{{ trans('panel::panel.tenancy.team_name') }}</Label>
                        <Input
                            id="name"
                            v-model="profileForm.name"
                            type="text"
                            :disabled="!permissions.canUpdateTeam"
                        />
                        <InputError :message="profileForm.errors.name" />
                    </div>

                    <!-- Description -->
                    <div class="space-y-2">
                        <Label for="description">{{ trans('panel::panel.tenancy.settings.team_description') }}</Label>
                        <Textarea
                            id="description"
                            v-model="profileForm.description"
                            :placeholder="trans('panel::panel.tenancy.settings.team_description_placeholder')"
                            :disabled="!permissions.canUpdateTeam"
                            rows="3"
                        />
                        <InputError :message="profileForm.errors.description" />
                    </div>

                    <!-- Team Settings -->
                    <div v-if="hasSettings && permissions.canUpdateTeam" class="space-y-4 border-t pt-6">
                        <div>
                            <h4 class="text-sm font-medium">{{ trans('panel::panel.tenancy.settings.team_settings') }}</h4>
                            <p class="text-sm text-muted-foreground">{{ trans('panel::panel.tenancy.settings.team_settings_description') }}</p>
                        </div>

                        <!-- Show Unassigned Records Toggle -->
                        <div class="flex items-start justify-between gap-4 rounded-lg border p-4">
                            <div class="space-y-1 flex-1">
                                <Label for="show_unassigned_records" class="text-sm font-medium cursor-pointer">
                                    {{ trans('panel::panel.tenancy.settings.show_unassigned_records') }}
                                </Label>
                                <p class="text-xs text-muted-foreground leading-relaxed">
                                    {{ trans('panel::panel.tenancy.settings.show_unassigned_records_description') }}
                                </p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer shrink-0">
                                <input
                                    type="checkbox"
                                    id="show_unassigned_records"
                                    v-model="showUnassignedRecords"
                                    class="sr-only peer"
                                />
                                <div class="w-11 h-6 bg-input peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-ring peer-focus:ring-offset-2 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-background after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
                            </label>
                        </div>
                    </div>

                    <div v-if="!permissions.canUpdateTeam" class="text-sm text-muted-foreground">
                        {{ trans('panel::panel.tenancy.settings.only_owner_can_edit') }}
                    </div>

                    <div v-if="permissions.canUpdateTeam" class="flex items-center gap-4">
                        <Button type="submit" :disabled="profileForm.processing">
                            {{ profileForm.processing ? trans('panel::panel.common.saving') : trans('panel::panel.common.save') }}
                        </Button>
                    </div>
                </form>
            </div>

            <!-- Danger Zone Section -->
            <div v-if="isOwner" class="flex flex-col space-y-6">
                <header>
                    <h3 class="mb-0.5 text-base font-medium">
                        {{ trans('panel::panel.tenancy.settings.danger_zone') }}
                    </h3>
                    <p class="text-sm text-muted-foreground">
                        {{ trans('panel::panel.tenancy.settings.danger_zone_description') }}
                    </p>
                </header>

                <div class="space-y-4">
                    <Dialog v-model:open="showDeleteDialog">
                        <DialogTrigger as-child>
                            <Button variant="destructive">
                                <Trash2 class="h-4 w-4 mr-2" />
                                {{ trans('panel::panel.tenancy.settings.delete_team') }}
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>
                                    {{ trans('panel::panel.tenancy.settings.delete_team_title') }}
                                </DialogTitle>
                                <DialogDescription>
                                    {{ trans('panel::panel.tenancy.settings.delete_team_description') }}
                                </DialogDescription>
                            </DialogHeader>
                            <DialogFooter>
                                <Button variant="outline" @click="showDeleteDialog = false">
                                    {{ trans('panel::panel.common.cancel') }}
                                </Button>
                                <Button
                                    variant="destructive"
                                    @click="deleteTeam"
                                    :disabled="isDeleting"
                                >
                                    {{ isDeleting ? trans('panel::panel.common.loading') : trans('panel::panel.tenancy.settings.delete_team') }}
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                </div>
            </div>
        </section>
    </SettingsLayout>
</template>
