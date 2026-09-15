import { Head, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Building2, Crown, Shield, Trash2, UserPlus } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useLocalization } from '@laravilt/support/composables/useLocalization';
import PanelLayout from '../../layouts/PanelLayout';

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

interface SettingsProps {
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

export default function Settings({ panel, team, members, availableRoles, permissions }: SettingsProps) {
    const { trans } = useLocalization();

    // Team Name Form
    const nameForm = useForm({
        name: team.name,
    });

    const updateTeamName = (event?: FormEvent) => {
        event?.preventDefault();
        nameForm.patch(`/${panel.path}/tenant-settings/name`, {
            preserveScroll: true,
            onSuccess: (page) => {
                // Update form with fresh data from server response
                const freshTeam = page.props.team as SettingsProps['team'];
                if (freshTeam) {
                    // Vue: defaults({ name }) + reset()
                    nameForm.setDefaults({ name: freshTeam.name });
                    nameForm.setData({ name: freshTeam.name });
                }
            },
        });
    };

    // Add Member Form
    const addMemberForm = useForm({
        email: '',
        role: 'member',
    });

    const [showAddMemberDialog, setShowAddMemberDialog] = useState(false);

    const addTeamMember = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        addMemberForm.post(`/${panel.path}/tenant-settings/members`, {
            preserveScroll: true,
            onSuccess: () => {
                addMemberForm.reset();
                setShowAddMemberDialog(false);
            },
        });
    };

    // Update Member Role
    const updateMemberRole = (memberId: number, role: string) => {
        router.patch(`/${panel.path}/tenant-settings/members/${memberId}/role`, { role }, { preserveScroll: true });
    };

    // Remove Member
    const [memberToRemove, setMemberToRemove] = useState<TeamMember | null>(null);
    const [showRemoveDialog, setShowRemoveDialog] = useState(false);

    const confirmRemoveMember = (member: TeamMember) => {
        setMemberToRemove(member);
        setShowRemoveDialog(true);
    };

    const removeMember = () => {
        if (!memberToRemove) return;

        router.delete(`/${panel.path}/tenant-settings/members/${memberToRemove.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                setShowRemoveDialog(false);
                setMemberToRemove(null);
            },
        });
    };

    // Delete Team
    const [showDeleteDialog, setShowDeleteDialog] = useState(false);

    const deleteTeam = () => {
        router.delete(`/${panel.path}/tenant-settings`, {
            onSuccess: () => {
                setShowDeleteDialog(false);
            },
        });
    };

    const goBack = () => {
        router.visit(`/${panel.path}`);
    };

    return (
        <PanelLayout>
            <Head title={trans('panel::panel.tenancy.tenant_settings')} />

            <div className="container max-w-4xl py-6 space-y-6">
                {/* Header */}
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="icon" onClick={goBack}>
                        <ArrowLeft className="h-4 w-4" />
                    </Button>
                    <div>
                        <h1 className="text-2xl font-bold">{trans('panel::panel.tenancy.tenant_settings')}</h1>
                        <p className="text-muted-foreground">{trans('panel::panel.tenancy.settings.description')}</p>
                    </div>
                </div>

                {/* Team Name Section */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Building2 className="h-5 w-5" />
                            {trans('panel::panel.tenancy.settings.team_name_section')}
                        </CardTitle>
                        <CardDescription>{trans('panel::panel.tenancy.settings.team_name_description')}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={updateTeamName} className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="name">{trans('panel::panel.tenancy.team_name')}</Label>
                                <Input
                                    id="name"
                                    value={nameForm.data.name}
                                    onChange={(event) => nameForm.setData('name', event.target.value)}
                                    type="text"
                                    disabled={!permissions.canUpdateTeam}
                                />
                                <InputError message={nameForm.errors.name} />
                            </div>
                        </form>
                    </CardContent>
                    {permissions.canUpdateTeam && (
                        <CardFooter>
                            <Button onClick={() => updateTeamName()} disabled={nameForm.processing}>
                                {nameForm.processing ? trans('panel::panel.common.saving') : trans('panel::panel.common.save')}
                            </Button>
                        </CardFooter>
                    )}
                </Card>

                {/* Team Members Section */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle className="flex items-center gap-2">
                                    <Shield className="h-5 w-5" />
                                    {trans('panel::panel.tenancy.settings.team_members_section')}
                                </CardTitle>
                                <CardDescription>{trans('panel::panel.tenancy.settings.team_members_description')}</CardDescription>
                            </div>
                            <Dialog open={showAddMemberDialog} onOpenChange={setShowAddMemberDialog}>
                                {permissions.canAddTeamMembers && (
                                    <DialogTrigger asChild>
                                        <Button size="sm">
                                            <UserPlus className="h-4 w-4 mr-2" />
                                            {trans('panel::panel.tenancy.settings.add_member')}
                                        </Button>
                                    </DialogTrigger>
                                )}
                                <DialogContent>
                                    <DialogHeader>
                                        <DialogTitle>{trans('panel::panel.tenancy.settings.add_member')}</DialogTitle>
                                        <DialogDescription>{trans('panel::panel.tenancy.settings.add_member_description')}</DialogDescription>
                                    </DialogHeader>
                                    <form onSubmit={addTeamMember} className="space-y-4">
                                        <div className="space-y-2">
                                            <Label htmlFor="email">{trans('panel::panel.tenancy.settings.email')}</Label>
                                            <Input
                                                id="email"
                                                value={addMemberForm.data.email}
                                                onChange={(event) => addMemberForm.setData('email', event.target.value)}
                                                type="email"
                                                placeholder={trans('panel::panel.tenancy.settings.email_placeholder')}
                                            />
                                            <InputError message={addMemberForm.errors.email} />
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="role">{trans('panel::panel.tenancy.settings.role')}</Label>
                                            <Select
                                                value={addMemberForm.data.role}
                                                onValueChange={(value) => addMemberForm.setData('role', value)}
                                            >
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {availableRoles.map((role) => (
                                                        <SelectItem key={role.key} value={role.key}>
                                                            <div>
                                                                <div>{role.name}</div>
                                                                <div className="text-xs text-muted-foreground">{role.description}</div>
                                                            </div>
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            <InputError message={addMemberForm.errors.role} />
                                        </div>
                                        <DialogFooter>
                                            <DialogClose asChild>
                                                <Button type="button" variant="outline">
                                                    {trans('panel::panel.common.cancel')}
                                                </Button>
                                            </DialogClose>
                                            <Button type="submit" disabled={addMemberForm.processing}>
                                                {trans('panel::panel.tenancy.settings.add_member')}
                                            </Button>
                                        </DialogFooter>
                                    </form>
                                </DialogContent>
                            </Dialog>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            {members.map((member) => (
                                <div key={member.id} className="flex items-center justify-between p-4 border rounded-lg">
                                    <div className="flex items-center gap-3">
                                        <Avatar className="h-10 w-10">
                                            <AvatarFallback>{getInitials(member.name)}</AvatarFallback>
                                        </Avatar>
                                        <div>
                                            <div className="flex items-center gap-2">
                                                <span className="font-medium">{member.name}</span>
                                                {member.is_owner && <Crown className="h-4 w-4 text-yellow-500" />}
                                            </div>
                                            <span className="text-sm text-muted-foreground">{member.email}</span>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        {member.is_owner ? (
                                            <Badge variant="default">{trans('panel::panel.tenancy.settings.owner')}</Badge>
                                        ) : (
                                            <>
                                                {permissions.canAddTeamMembers ? (
                                                    <Select
                                                        value={member.role}
                                                        onValueChange={(value) => updateMemberRole(member.id, value)}
                                                    >
                                                        <SelectTrigger className="w-32">
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {availableRoles.map((role) => (
                                                                <SelectItem key={role.key} value={role.key}>
                                                                    {role.name}
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                ) : (
                                                    <Badge variant={getRoleBadgeVariant(member.role)}>{member.role}</Badge>
                                                )}

                                                {permissions.canRemoveTeamMembers && (
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="text-destructive hover:text-destructive"
                                                        onClick={() => confirmRemoveMember(member)}
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                )}
                                            </>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>

                {/* Remove Member Dialog */}
                <Dialog open={showRemoveDialog} onOpenChange={setShowRemoveDialog}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>{trans('panel::panel.tenancy.settings.remove_member_title')}</DialogTitle>
                            <DialogDescription>
                                {trans('panel::panel.tenancy.settings.remove_member_description', {
                                    name: memberToRemove?.name,
                                } as Record<string, string>)}
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <Button variant="outline" onClick={() => setShowRemoveDialog(false)}>
                                {trans('panel::panel.common.cancel')}
                            </Button>
                            <Button variant="destructive" onClick={removeMember}>
                                {trans('panel::panel.tenancy.settings.remove')}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>

                {/* Danger Zone */}
                {permissions.canDeleteTeam && (
                    <Card className="border-destructive">
                        <CardHeader>
                            <CardTitle className="text-destructive">{trans('panel::panel.tenancy.settings.danger_zone')}</CardTitle>
                            <CardDescription>{trans('panel::panel.tenancy.settings.danger_zone_description')}</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Dialog open={showDeleteDialog} onOpenChange={setShowDeleteDialog}>
                                <DialogTrigger asChild>
                                    <Button variant="destructive">
                                        <Trash2 className="h-4 w-4 mr-2" />
                                        {trans('panel::panel.tenancy.settings.delete_team')}
                                    </Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <DialogHeader>
                                        <DialogTitle>{trans('panel::panel.tenancy.settings.delete_team_title')}</DialogTitle>
                                        <DialogDescription>{trans('panel::panel.tenancy.settings.delete_team_description')}</DialogDescription>
                                    </DialogHeader>
                                    <DialogFooter>
                                        <Button variant="outline" onClick={() => setShowDeleteDialog(false)}>
                                            {trans('panel::panel.common.cancel')}
                                        </Button>
                                        <Button variant="destructive" onClick={deleteTeam}>
                                            {trans('panel::panel.tenancy.settings.delete_team')}
                                        </Button>
                                    </DialogFooter>
                                </DialogContent>
                            </Dialog>
                        </CardContent>
                    </Card>
                )}
            </div>
        </PanelLayout>
    );
}
