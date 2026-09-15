import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Trash2, Upload, X } from 'lucide-react';
import { useRef, useState, type ChangeEvent, type FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import SettingsLayout from '@laravilt/panel/layouts/SettingsLayout';
import { useLocalization } from '@laravilt/support/composables';

interface NavigationItem {
    title: string;
    href: string;
    icon?: string;
    active?: boolean;
}

interface TeamProfileProps {
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

interface ProfileFormData {
    name: string;
    description: string;
    avatar: File | null;
    remove_avatar: boolean;
    show_unassigned_records: boolean;
}

const getInitials = (name: string): string => {
    return name
        .split(' ')
        .map((word) => word[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);
};

export default function TeamProfile({
    page,
    panelId,
    team,
    isOwner,
    hasSettings,
    permissions,
    routes,
    clusterNavigation,
    clusterTitle,
    clusterDescription,
}: TeamProfileProps) {
    const { trans } = useLocalization();
    const inertiaPage = usePage();
    const panelLogo = ((inertiaPage.props as any)?.panel?.brandLogo ?? null) as string | null;

    // Team Profile Form
    const profileForm = useForm<ProfileFormData>({
        name: team.name,
        description: team.description || '',
        avatar: null,
        remove_avatar: false,
        show_unassigned_records: team.settings?.show_unassigned_records ?? false,
    });

    // Separate state for switch to ensure proper binding
    const [showUnassignedRecords, setShowUnassignedRecords] = useState<boolean>(
        team.settings?.show_unassigned_records ?? false,
    );

    // Avatar preview
    const [avatarPreview, setAvatarPreview] = useState<string | null>(team.avatar || null);
    const avatarInput = useRef<HTMLInputElement | null>(null);

    const selectAvatar = () => {
        avatarInput.current?.click();
    };

    const handleAvatarChange = (event: ChangeEvent<HTMLInputElement>) => {
        const target = event.target;
        const file = target.files?.[0];

        if (file) {
            profileForm.setData((data) => ({ ...data, avatar: file, remove_avatar: false }));

            // Create preview
            const reader = new FileReader();
            reader.onload = (e) => {
                setAvatarPreview(e.target?.result as string);
            };
            reader.readAsDataURL(file);
        }
    };

    const removeAvatar = () => {
        profileForm.setData((data) => ({ ...data, avatar: null, remove_avatar: true }));
        setAvatarPreview(null);
        if (avatarInput.current) {
            avatarInput.current.value = '';
        }
    };

    const updateTeamProfile = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        // Sync the switch value to the form before submitting
        profileForm.setData('show_unassigned_records', showUnassignedRecords);
        profileForm.transform((data) => ({ ...data, show_unassigned_records: showUnassignedRecords }));

        profileForm.post(`/${panelId}/tenant-settings/profile`, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: (successPage) => {
                // Update form with fresh data from server response
                const freshTeam = successPage.props.team as TeamProfileProps['team'];
                if (freshTeam) {
                    const freshData: ProfileFormData = {
                        name: freshTeam.name,
                        description: freshTeam.description || '',
                        avatar: null,
                        remove_avatar: false,
                        show_unassigned_records: freshTeam.settings?.show_unassigned_records ?? false,
                    };
                    // Vue: defaults(fresh) + reset()
                    profileForm.setDefaults(freshData);
                    profileForm.setData(freshData);
                    setShowUnassignedRecords(freshTeam.settings?.show_unassigned_records ?? false);
                    setAvatarPreview(freshTeam.avatar || null);
                }
            },
        });
    };

    // Delete Team
    const [showDeleteDialog, setShowDeleteDialog] = useState(false);
    const [isDeleting, setIsDeleting] = useState(false);

    const deleteTeam = () => {
        if (!routes?.deleteTeam) return;

        setIsDeleting(true);
        router.delete(routes.deleteTeam, {
            onSuccess: () => {
                setShowDeleteDialog(false);
            },
            onFinish: () => {
                setIsDeleting(false);
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
                    {/* Team Profile Section */}
                    <div className="flex flex-col space-y-6">
                        <header>
                            <h3 className="mb-0.5 text-base font-medium">{page.heading}</h3>
                            {page.subheading && <p className="text-sm text-muted-foreground">{page.subheading}</p>}
                        </header>

                        <form onSubmit={updateTeamProfile} className="space-y-6">
                            {/* Avatar */}
                            <div className="space-y-2">
                                <Label>{trans('panel::panel.tenancy.settings.team_avatar')}</Label>
                                <div className="flex items-center gap-4">
                                    <Avatar className="h-20 w-20 rounded-lg">
                                        {avatarPreview ? (
                                            <AvatarImage src={avatarPreview} />
                                        ) : panelLogo ? (
                                            <AvatarImage src={panelLogo} />
                                        ) : null}
                                        <AvatarFallback className="rounded-lg text-lg">
                                            {getInitials(profileForm.data.name || team.name)}
                                        </AvatarFallback>
                                    </Avatar>
                                    <div className="flex flex-col gap-2">
                                        <input
                                            ref={avatarInput}
                                            type="file"
                                            accept="image/*"
                                            className="hidden"
                                            onChange={handleAvatarChange}
                                            disabled={!permissions.canUpdateTeam}
                                        />
                                        <div className="flex gap-2">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={selectAvatar}
                                                disabled={!permissions.canUpdateTeam}
                                            >
                                                <Upload className="h-4 w-4 me-2" />
                                                {trans('panel::panel.tenancy.settings.upload_avatar')}
                                            </Button>
                                            {avatarPreview && (
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={removeAvatar}
                                                    disabled={!permissions.canUpdateTeam}
                                                >
                                                    <X className="h-4 w-4 me-2" />
                                                    {trans('panel::panel.tenancy.settings.remove_avatar')}
                                                </Button>
                                            )}
                                        </div>
                                        <p className="text-xs text-muted-foreground">
                                            {trans('panel::panel.tenancy.settings.avatar_hint')}
                                        </p>
                                    </div>
                                </div>
                                <InputError message={profileForm.errors.avatar} />
                            </div>

                            {/* Team Name */}
                            <div className="space-y-2">
                                <Label htmlFor="name">{trans('panel::panel.tenancy.team_name')}</Label>
                                <Input
                                    id="name"
                                    value={profileForm.data.name}
                                    onChange={(event) => profileForm.setData('name', event.target.value)}
                                    type="text"
                                    disabled={!permissions.canUpdateTeam}
                                />
                                <InputError message={profileForm.errors.name} />
                            </div>

                            {/* Description */}
                            <div className="space-y-2">
                                <Label htmlFor="description">{trans('panel::panel.tenancy.settings.team_description')}</Label>
                                <Textarea
                                    id="description"
                                    value={profileForm.data.description}
                                    onChange={(event) => profileForm.setData('description', event.target.value)}
                                    placeholder={trans('panel::panel.tenancy.settings.team_description_placeholder')}
                                    disabled={!permissions.canUpdateTeam}
                                    rows={3}
                                />
                                <InputError message={profileForm.errors.description} />
                            </div>

                            {/* Team Settings */}
                            {hasSettings && permissions.canUpdateTeam && (
                                <div className="space-y-4 border-t pt-6">
                                    <div>
                                        <h4 className="text-sm font-medium">{trans('panel::panel.tenancy.settings.team_settings')}</h4>
                                        <p className="text-sm text-muted-foreground">
                                            {trans('panel::panel.tenancy.settings.team_settings_description')}
                                        </p>
                                    </div>

                                    {/* Show Unassigned Records Toggle */}
                                    <div className="flex items-start justify-between gap-4 rounded-lg border p-4">
                                        <div className="space-y-1 flex-1">
                                            <Label htmlFor="show_unassigned_records" className="text-sm font-medium cursor-pointer">
                                                {trans('panel::panel.tenancy.settings.show_unassigned_records')}
                                            </Label>
                                            <p className="text-xs text-muted-foreground leading-relaxed">
                                                {trans('panel::panel.tenancy.settings.show_unassigned_records_description')}
                                            </p>
                                        </div>
                                        <label className="relative inline-flex items-center cursor-pointer shrink-0">
                                            <input
                                                type="checkbox"
                                                id="show_unassigned_records"
                                                checked={showUnassignedRecords}
                                                onChange={(event) => setShowUnassignedRecords(event.target.checked)}
                                                className="sr-only peer"
                                            />
                                            <div className="w-11 h-6 bg-input peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-ring peer-focus:ring-offset-2 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-background after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
                                        </label>
                                    </div>
                                </div>
                            )}

                            {!permissions.canUpdateTeam && (
                                <div className="text-sm text-muted-foreground">
                                    {trans('panel::panel.tenancy.settings.only_owner_can_edit')}
                                </div>
                            )}

                            {permissions.canUpdateTeam && (
                                <div className="flex items-center gap-4">
                                    <Button type="submit" disabled={profileForm.processing}>
                                        {profileForm.processing
                                            ? trans('panel::panel.common.saving')
                                            : trans('panel::panel.common.save')}
                                    </Button>
                                </div>
                            )}
                        </form>
                    </div>

                    {/* Danger Zone Section */}
                    {isOwner && (
                        <div className="flex flex-col space-y-6">
                            <header>
                                <h3 className="mb-0.5 text-base font-medium">{trans('panel::panel.tenancy.settings.danger_zone')}</h3>
                                <p className="text-sm text-muted-foreground">
                                    {trans('panel::panel.tenancy.settings.danger_zone_description')}
                                </p>
                            </header>

                            <div className="space-y-4">
                                <Dialog open={showDeleteDialog} onOpenChange={setShowDeleteDialog}>
                                    <DialogTrigger asChild>
                                        <Button variant="destructive">
                                            <Trash2 className="h-4 w-4 me-2" />
                                            {trans('panel::panel.tenancy.settings.delete_team')}
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent>
                                        <DialogHeader>
                                            <DialogTitle>{trans('panel::panel.tenancy.settings.delete_team_title')}</DialogTitle>
                                            <DialogDescription>
                                                {trans('panel::panel.tenancy.settings.delete_team_description')}
                                            </DialogDescription>
                                        </DialogHeader>
                                        <DialogFooter>
                                            <Button variant="outline" onClick={() => setShowDeleteDialog(false)}>
                                                {trans('panel::panel.common.cancel')}
                                            </Button>
                                            <Button variant="destructive" onClick={deleteTeam} disabled={isDeleting}>
                                                {isDeleting
                                                    ? trans('panel::panel.common.loading')
                                                    : trans('panel::panel.tenancy.settings.delete_team')}
                                            </Button>
                                        </DialogFooter>
                                    </DialogContent>
                                </Dialog>
                            </div>
                        </div>
                    )}
                </section>
            </SettingsLayout>
        </>
    );
}
