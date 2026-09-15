import { Head, useForm } from '@inertiajs/react';
import { Building2 } from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useLocalization } from '@laravilt/support/composables/useLocalization';

interface RegisterProps {
    panel: {
        id: string;
        path: string;
    };
}

export default function Register({ panel }: RegisterProps) {
    const { trans } = useLocalization();

    const form = useForm({
        name: '',
        slug: '',
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(`/${panel.path}/tenant/register`);
    };

    return (
        <>
            <Head title={trans('panel::panel.tenancy.create_tenant')} />

            <div className="min-h-screen flex items-center justify-center bg-background p-4">
                <Card className="w-full max-w-md">
                    <CardHeader className="text-center">
                        <div className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-primary/10">
                            <Building2 className="h-6 w-6 text-primary" />
                        </div>
                        <CardTitle className="text-2xl">{trans('panel::panel.tenancy.create_tenant')}</CardTitle>
                        <CardDescription>{trans('panel::panel.tenancy.create_tenant_description')}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="name">{trans('panel::panel.tenancy.team_name')}</Label>
                                <Input
                                    id="name"
                                    value={form.data.name}
                                    onChange={(event) => form.setData('name', event.target.value)}
                                    type="text"
                                    placeholder={trans('panel::panel.tenancy.team_name_placeholder')}
                                    required
                                    autoFocus
                                />
                                <InputError message={form.errors.name} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="slug">{trans('panel::panel.tenancy.team_slug')}</Label>
                                <Input
                                    id="slug"
                                    value={form.data.slug}
                                    onChange={(event) => form.setData('slug', event.target.value)}
                                    type="text"
                                    placeholder={trans('panel::panel.tenancy.team_slug_placeholder')}
                                />
                                <p className="text-xs text-muted-foreground">{trans('panel::panel.tenancy.team_slug_help')}</p>
                                <InputError message={form.errors.slug} />
                            </div>

                            <Button type="submit" className="w-full" disabled={form.processing}>
                                {form.processing ? (
                                    <span>{trans('panel::panel.common.loading')}</span>
                                ) : (
                                    <span>{trans('panel::panel.tenancy.create_tenant')}</span>
                                )}
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
