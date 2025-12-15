<script setup lang="ts">
import { ref } from 'vue';
import { useForm, Head, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
    DialogClose,
} from '@/components/ui/dialog';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { UserPlus, Trash2, Crown } from 'lucide-vue-next';
import { useLocalization } from '@laravilt/support/composables';
import InputError from '@/components/InputError.vue';
import SettingsLayout from '@laravilt/panel/layouts/SettingsLayout.vue';

const { trans } = useLocalization();

interface TeamMember {
    id: number;
    name: string;
    email: string;
    role: string;
    is_owner: boolean;
}

interface Role {
    key: string;
    name: string;
    description: string;
}

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
    };
    members: TeamMember[];
    isOwner: boolean;
    availableRoles: Role[];
    permissions: {
        canAddTeamMembers: boolean;
        canRemoveTeamMembers: boolean;
        canUpdateMemberRole: boolean;
    };
    routes: {
        addMember: string;
        updateRole: string;
        removeMember: string;
    };
    clusterNavigation?: NavigationItem[];
    clusterTitle?: string;
    clusterDescription?: string;
}

const props = defineProps<Props>();

// Add Member Form
const addMemberForm = useForm({
    email: '',
    role: 'member',
    send_email: true,
    send_database: true,
});

const showAddMemberDialog = ref(false);

const inviteTeamMember = () => {
    addMemberForm.post(props.routes.addMember, {
        preserveScroll: true,
        onSuccess: () => {
            addMemberForm.reset();
            showAddMemberDialog.value = false;
        },
    });
};

// Update Member Role
const updateMemberRole = (memberId: number, role: string) => {
    const url = props.routes.updateRole.replace('{id}', String(memberId));
    router.patch(url, { role }, { preserveScroll: true });
};

// Remove Member
const memberToRemove = ref<TeamMember | null>(null);
const showRemoveDialog = ref(false);

const confirmRemoveMember = (member: TeamMember) => {
    memberToRemove.value = member;
    showRemoveDialog.value = true;
};

const removeMember = () => {
    if (!memberToRemove.value) return;

    const url = props.routes.removeMember.replace('{id}', String(memberToRemove.value.id));
    router.delete(url, {
        preserveScroll: true,
        onSuccess: () => {
            showRemoveDialog.value = false;
            memberToRemove.value = null;
        },
    });
};

const getInitials = (name: string): string => {
    return name
        .split(' ')
        .map((word) => word[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);
};

const getRoleBadgeVariant = (role: string): 'default' | 'secondary' | 'outline' => {
    switch (role) {
        case 'owner':
            return 'default';
        case 'admin':
            return 'secondary';
        default:
            return 'outline';
    }
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
            <div class="flex flex-col space-y-6">
                <header class="flex items-center justify-between">
                    <div>
                        <h3 class="mb-0.5 text-base font-medium">
                            {{ page.heading }}
                        </h3>
                        <p v-if="page.subheading" class="text-sm text-muted-foreground">
                            {{ page.subheading }}
                        </p>
                    </div>

                    <!-- Invite Member Button -->
                    <Dialog v-model:open="showAddMemberDialog">
                        <DialogTrigger as-child>
                            <Button v-if="permissions.canAddTeamMembers" size="sm">
                                <UserPlus class="h-4 w-4 mr-2" />
                                {{ trans('panel::panel.tenancy.settings.invite_member') }}
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>{{ trans('panel::panel.tenancy.settings.invite_member') }}</DialogTitle>
                                <DialogDescription>
                                    {{ trans('panel::panel.tenancy.settings.invite_member_description') }}
                                </DialogDescription>
                            </DialogHeader>
                            <form @submit.prevent="inviteTeamMember" class="space-y-4">
                                <div class="space-y-2">
                                    <Label for="email">{{ trans('panel::panel.tenancy.settings.email') }}</Label>
                                    <Input
                                        id="email"
                                        v-model="addMemberForm.email"
                                        type="email"
                                        :placeholder="trans('panel::panel.tenancy.settings.email_placeholder')"
                                    />
                                    <InputError :message="addMemberForm.errors.email" />
                                </div>
                                <div class="space-y-2">
                                    <Label for="role">{{ trans('panel::panel.tenancy.settings.role') }}</Label>
                                    <Select v-model="addMemberForm.role">
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="role in availableRoles"
                                                :key="role.key"
                                                :value="role.key"
                                            >
                                                <div>
                                                    <div>{{ role.name }}</div>
                                                    <div class="text-xs text-muted-foreground">{{ role.description }}</div>
                                                </div>
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError :message="addMemberForm.errors.role" />
                                </div>
                                <div class="space-y-3">
                                    <div class="flex items-center space-x-2">
                                        <Checkbox
                                            id="send_email"
                                            :checked="addMemberForm.send_email"
                                            @update:checked="addMemberForm.send_email = $event"
                                        />
                                        <Label for="send_email" class="text-sm font-normal cursor-pointer">
                                            {{ trans('panel::panel.tenancy.settings.send_email_notification') }}
                                        </Label>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <Checkbox
                                            id="send_database"
                                            :checked="addMemberForm.send_database"
                                            @update:checked="addMemberForm.send_database = $event"
                                        />
                                        <Label for="send_database" class="text-sm font-normal cursor-pointer">
                                            {{ trans('panel::panel.tenancy.settings.send_notification_center') }}
                                        </Label>
                                    </div>
                                </div>
                                <DialogFooter>
                                    <DialogClose as-child>
                                        <Button type="button" variant="outline">
                                            {{ trans('panel::panel.common.cancel') }}
                                        </Button>
                                    </DialogClose>
                                    <Button type="submit" :disabled="addMemberForm.processing">
                                        {{ trans('panel::panel.tenancy.settings.invite_member') }}
                                    </Button>
                                </DialogFooter>
                            </form>
                        </DialogContent>
                    </Dialog>
                </header>

                <!-- Members List -->
                <div class="space-y-4">
                    <div v-if="members.length === 0" class="text-center py-8 text-muted-foreground">
                        {{ trans('panel::panel.tenancy.settings.no_members') }}
                    </div>
                    <div
                        v-for="member in members"
                        :key="member.id"
                        class="flex items-center justify-between p-4 border rounded-lg"
                    >
                        <div class="flex items-center gap-3">
                            <Avatar class="h-10 w-10">
                                <AvatarFallback>{{ getInitials(member.name) }}</AvatarFallback>
                            </Avatar>
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-medium">{{ member.name }}</span>
                                    <Crown v-if="member.is_owner" class="h-4 w-4 text-yellow-500" />
                                </div>
                                <span class="text-sm text-muted-foreground">{{ member.email }}</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <template v-if="member.is_owner">
                                <Badge variant="default">
                                    {{ trans('panel::panel.tenancy.settings.owner') }}
                                </Badge>
                            </template>
                            <template v-else>
                                <Select
                                    v-if="permissions.canUpdateMemberRole"
                                    :model-value="member.role"
                                    @update:model-value="(val) => updateMemberRole(member.id, val as string)"
                                >
                                    <SelectTrigger class="w-32">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="role in availableRoles"
                                            :key="role.key"
                                            :value="role.key"
                                        >
                                            {{ role.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <Badge v-else :variant="getRoleBadgeVariant(member.role)">
                                    {{ member.role }}
                                </Badge>

                                <Button
                                    v-if="permissions.canRemoveTeamMembers"
                                    variant="ghost"
                                    size="icon"
                                    class="text-destructive hover:text-destructive"
                                    @click="confirmRemoveMember(member)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Remove Member Dialog -->
        <Dialog v-model:open="showRemoveDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ trans('panel::panel.tenancy.settings.remove_member_title') }}</DialogTitle>
                    <DialogDescription>
                        {{ trans('panel::panel.tenancy.settings.remove_member_description', { name: memberToRemove?.name }) }}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="showRemoveDialog = false">
                        {{ trans('panel::panel.common.cancel') }}
                    </Button>
                    <Button variant="destructive" @click="removeMember">
                        {{ trans('panel::panel.tenancy.settings.remove') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </SettingsLayout>
</template>
