<script setup lang="ts">
import { ref, computed } from 'vue';
import { useForm, Head, router, usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle, CardFooter } from '@/components/ui/card';
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
import { Building2, UserPlus, Trash2, Shield, Crown, ArrowLeft } from 'lucide-vue-next';
import { useLocalization } from '@/composables/useLocalization';
import InputError from '@/components/InputError.vue';
import PanelLayout from '../../layouts/PanelLayout.vue';

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

interface Props {
    panel: {
        id: string;
        path: string;
    };
    team: {
        id: number;
        name: string;
        slug: string;
        owner_id: number | null;
    };
    members: TeamMember[];
    isOwner: boolean;
    availableRoles: Role[];
    permissions: {
        canUpdateTeam: boolean;
        canDeleteTeam: boolean;
        canAddTeamMembers: boolean;
        canRemoveTeamMembers: boolean;
    };
}

const props = defineProps<Props>();

// Team Name Form
const nameForm = useForm({
    name: props.team.name,
});

const updateTeamName = () => {
    nameForm.patch(`/${props.panel.path}/tenant-settings/name`, {
        preserveScroll: true,
        onSuccess: (page) => {
            // Update form with fresh data from server response
            const team = page.props.team as Props['team'];
            if (team) {
                nameForm.defaults({ name: team.name });
                nameForm.reset();
            }
        },
    });
};

// Add Member Form
const addMemberForm = useForm({
    email: '',
    role: 'member',
});

const showAddMemberDialog = ref(false);

const addTeamMember = () => {
    addMemberForm.post(`/${props.panel.path}/tenant-settings/members`, {
        preserveScroll: true,
        onSuccess: () => {
            addMemberForm.reset();
            showAddMemberDialog.value = false;
        },
    });
};

// Update Member Role
const updateMemberRole = (memberId: number, role: string) => {
    router.patch(
        `/${props.panel.path}/tenant-settings/members/${memberId}/role`,
        { role },
        { preserveScroll: true }
    );
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

    router.delete(`/${props.panel.path}/tenant-settings/members/${memberToRemove.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showRemoveDialog.value = false;
            memberToRemove.value = null;
        },
    });
};

// Delete Team
const showDeleteDialog = ref(false);

const deleteTeam = () => {
    router.delete(`/${props.panel.path}/tenant-settings`, {
        onSuccess: () => {
            showDeleteDialog.value = false;
        },
    });
};

const goBack = () => {
    router.visit(`/${props.panel.path}`);
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
</script>

<template>
    <PanelLayout>
        <Head :title="trans('panel::panel.tenancy.tenant_settings')" />

        <div class="container max-w-4xl py-6 space-y-6">
            <!-- Header -->
            <div class="flex items-center gap-4">
                <Button variant="ghost" size="icon" @click="goBack">
                    <ArrowLeft class="h-4 w-4" />
                </Button>
                <div>
                    <h1 class="text-2xl font-bold">{{ trans('panel::panel.tenancy.tenant_settings') }}</h1>
                    <p class="text-muted-foreground">
                        {{ trans('panel::panel.tenancy.settings.description') }}
                    </p>
                </div>
            </div>

            <!-- Team Name Section -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Building2 class="h-5 w-5" />
                        {{ trans('panel::panel.tenancy.settings.team_name_section') }}
                    </CardTitle>
                    <CardDescription>
                        {{ trans('panel::panel.tenancy.settings.team_name_description') }}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="updateTeamName" class="space-y-4">
                        <div class="space-y-2">
                            <Label for="name">{{ trans('panel::panel.tenancy.team_name') }}</Label>
                            <Input
                                id="name"
                                v-model="nameForm.name"
                                type="text"
                                :disabled="!permissions.canUpdateTeam"
                            />
                            <InputError :message="nameForm.errors.name" />
                        </div>
                    </form>
                </CardContent>
                <CardFooter v-if="permissions.canUpdateTeam">
                    <Button
                        @click="updateTeamName"
                        :disabled="nameForm.processing"
                    >
                        {{ nameForm.processing ? trans('panel::panel.common.saving') : trans('panel::panel.common.save') }}
                    </Button>
                </CardFooter>
            </Card>

            <!-- Team Members Section -->
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle class="flex items-center gap-2">
                                <Shield class="h-5 w-5" />
                                {{ trans('panel::panel.tenancy.settings.team_members_section') }}
                            </CardTitle>
                            <CardDescription>
                                {{ trans('panel::panel.tenancy.settings.team_members_description') }}
                            </CardDescription>
                        </div>
                        <Dialog v-model:open="showAddMemberDialog">
                            <DialogTrigger as-child>
                                <Button v-if="permissions.canAddTeamMembers" size="sm">
                                    <UserPlus class="h-4 w-4 mr-2" />
                                    {{ trans('panel::panel.tenancy.settings.add_member') }}
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogHeader>
                                    <DialogTitle>{{ trans('panel::panel.tenancy.settings.add_member') }}</DialogTitle>
                                    <DialogDescription>
                                        {{ trans('panel::panel.tenancy.settings.add_member_description') }}
                                    </DialogDescription>
                                </DialogHeader>
                                <form @submit.prevent="addTeamMember" class="space-y-4">
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
                                    <DialogFooter>
                                        <DialogClose as-child>
                                            <Button type="button" variant="outline">
                                                {{ trans('panel::panel.common.cancel') }}
                                            </Button>
                                        </DialogClose>
                                        <Button type="submit" :disabled="addMemberForm.processing">
                                            {{ trans('panel::panel.tenancy.settings.add_member') }}
                                        </Button>
                                    </DialogFooter>
                                </form>
                            </DialogContent>
                        </Dialog>
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="space-y-4">
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
                                        v-if="permissions.canAddTeamMembers"
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
                </CardContent>
            </Card>

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

            <!-- Danger Zone -->
            <Card v-if="permissions.canDeleteTeam" class="border-destructive">
                <CardHeader>
                    <CardTitle class="text-destructive">
                        {{ trans('panel::panel.tenancy.settings.danger_zone') }}
                    </CardTitle>
                    <CardDescription>
                        {{ trans('panel::panel.tenancy.settings.danger_zone_description') }}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <Dialog v-model:open="showDeleteDialog">
                        <DialogTrigger as-child>
                            <Button variant="destructive">
                                <Trash2 class="h-4 w-4 mr-2" />
                                {{ trans('panel::panel.tenancy.settings.delete_team') }}
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>{{ trans('panel::panel.tenancy.settings.delete_team_title') }}</DialogTitle>
                                <DialogDescription>
                                    {{ trans('panel::panel.tenancy.settings.delete_team_description') }}
                                </DialogDescription>
                            </DialogHeader>
                            <DialogFooter>
                                <Button variant="outline" @click="showDeleteDialog = false">
                                    {{ trans('panel::panel.common.cancel') }}
                                </Button>
                                <Button variant="destructive" @click="deleteTeam">
                                    {{ trans('panel::panel.tenancy.settings.delete_team') }}
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                </CardContent>
            </Card>
        </div>
    </PanelLayout>
</template>
