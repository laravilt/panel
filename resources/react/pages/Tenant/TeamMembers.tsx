import { Head, router, useForm } from '@inertiajs/react';
import { Crown, Trash2, UserPlus } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import SettingsLayout from '@laravilt/panel/layouts/SettingsLayout';
import { useLocalization } from '@laravilt/support/composables';

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

interface TeamMembersProps {
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

export default function TeamMembers({
    page,
    members,
    availableRoles,
    permissions,
    routes,
    clusterNavigation,
    clusterTitle,
    clusterDescription,
}: TeamMembersProps) {
    const { trans } = useLocalization();

    // Add Member Form
    const addMemberForm = useForm({
        email: '',
        role: 'member',
        send_email: true,
        send_database: true,
    });

    const [showAddMemberDialog, setShowAddMemberDialog] = useState(false);

    const inviteTeamMember = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        addMemberForm.post(routes.addMember, {
            preserveScroll: true,
            onSuccess: () => {
                addMemberForm.reset();
                setShowAddMemberDialog(false);
            },
        });
    };

    // Update Member Role
    const updateMemberRole = (memberId: number, role: string) => {
        const url = routes.updateRole.replace('{id}', String(memberId));
        router.patch(url, { role }, { preserveScroll: true });
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

        const url = routes.removeMember.replace('{id}', String(memberToRemove.id));
        router.delete(url, {
            preserveScroll: true,
            onSuccess: () => {
                setShowRemoveDialog(false);
                setMemberToRemove(null);
            },
        });
    };

    const layoutProps = {
        navigation: clusterNavigation,
        title: clusterTitle,
        description: clusterDescription,
    };

    return (
        <>
            <Head title={page.heading} />

            <SettingsLayout {...layoutProps}>
                <section className="max-w-xl space-y-12">
                    <div className="flex flex-col space-y-6">
                        <header className="flex items-center justify-between">
                            <div>
                                <h3 className="mb-0.5 text-base font-medium">{page.heading}</h3>
                                {page.subheading && <p className="text-sm text-muted-foreground">{page.subheading}</p>}
                            </div>

                            {/* Invite Member Button */}
                            <Dialog open={showAddMemberDialog} onOpenChange={setShowAddMemberDialog}>
                                {permissions.canAddTeamMembers && (
                                    <DialogTrigger asChild>
                                        <Button size="sm">
                                            <UserPlus className="h-4 w-4 me-2" />
                                            {trans('panel::panel.tenancy.settings.invite_member')}
                                        </Button>
                                    </DialogTrigger>
                                )}
                                <DialogContent>
                                    <DialogHeader className="text-start">
                                        <DialogTitle>{trans('panel::panel.tenancy.settings.invite_member')}</DialogTitle>
                                        <DialogDescription>{trans('panel::panel.tenancy.settings.invite_member_description')}</DialogDescription>
                                    </DialogHeader>
                                    <form onSubmit={inviteTeamMember} className="space-y-4">
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
                                        <div className="space-y-3">
                                            <div className="flex items-center gap-2">
                                                <Checkbox
                                                    id="send_email"
                                                    checked={addMemberForm.data.send_email}
                                                    onCheckedChange={(checked) => addMemberForm.setData('send_email', checked === true)}
                                                />
                                                <Label htmlFor="send_email" className="text-sm font-normal cursor-pointer">
                                                    {trans('panel::panel.tenancy.settings.send_email_notification')}
                                                </Label>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <Checkbox
                                                    id="send_database"
                                                    checked={addMemberForm.data.send_database}
                                                    onCheckedChange={(checked) => addMemberForm.setData('send_database', checked === true)}
                                                />
                                                <Label htmlFor="send_database" className="text-sm font-normal cursor-pointer">
                                                    {trans('panel::panel.tenancy.settings.send_notification_center')}
                                                </Label>
                                            </div>
                                        </div>
                                        <DialogFooter>
                                            <DialogClose asChild>
                                                <Button type="button" variant="outline">
                                                    {trans('panel::panel.common.cancel')}
                                                </Button>
                                            </DialogClose>
                                            <Button type="submit" disabled={addMemberForm.processing}>
                                                {trans('panel::panel.tenancy.settings.invite_member')}
                                            </Button>
                                        </DialogFooter>
                                    </form>
                                </DialogContent>
                            </Dialog>
                        </header>

                        {/* Members List */}
                        <div className="space-y-4">
                            {members.length === 0 && (
                                <div className="text-center py-8 text-muted-foreground">
                                    {trans('panel::panel.tenancy.settings.no_members')}
                                </div>
                            )}
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
                                                {permissions.canUpdateMemberRole ? (
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
                                                        aria-label={trans('panel::panel.tenancy.settings.remove')}
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
                    </div>
                </section>

                {/* Remove Member Dialog */}
                <Dialog open={showRemoveDialog} onOpenChange={setShowRemoveDialog}>
                    <DialogContent>
                        <DialogHeader className="text-start">
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
            </SettingsLayout>
        </>
    );
}
