import { Head, useForm } from '@inertiajs/react';
import { AlertCircle, Building2, Globe } from 'lucide-react';
import { useEffect, useState, type FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useLocalization } from '@laravilt/support/composables/useLocalization';

interface RegisterMultiDbProps {
    panel: {
        id: string;
        path: string;
    };
    baseDomain: string;
    reservedSubdomains: string[];
    isMultiDatabase: boolean;
}

export default function RegisterMultiDb({ panel, baseDomain, reservedSubdomains }: RegisterMultiDbProps) {
    const { trans } = useLocalization();

    const form = useForm({
        name: '',
        subdomain: '',
        description: '',
    });

    // Auto-generate subdomain from name
    const [autoGenerateSubdomain, setAutoGenerateSubdomain] = useState(true);

    // watch(() => form.name)
    useEffect(() => {
        const newName = form.data.name;
        if (autoGenerateSubdomain && newName) {
            form.setData(
                'subdomain',
                newName
                    .toLowerCase()
                    .replace(/[^a-z0-9]+/g, '-')
                    .replace(/^-|-$/g, '')
                    .substring(0, 63),
            );
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [form.data.name]);

    // When user manually edits subdomain, stop auto-generation
    const onSubdomainInput = () => {
        setAutoGenerateSubdomain(false);
    };

    // Validate subdomain format
    const subdomainError: string | null = (() => {
        if (!form.data.subdomain) return null;

        if (!/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/.test(form.data.subdomain)) {
            return trans('panel::panel.tenancy.subdomain_invalid');
        }

        if (reservedSubdomains.includes(form.data.subdomain)) {
            return trans('panel::panel.tenancy.subdomain_reserved');
        }

        return null;
    })();

    // Full domain preview
    const fullDomain = !form.data.subdomain ? `your-team.${baseDomain}` : `${form.data.subdomain}.${baseDomain}`;

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (subdomainError) return;
        form.post(`/${panel.path}/tenant/register`);
    };

    return (
        <>
            <Head title={trans('panel::panel.tenancy.create_tenant')} />

            <div className="min-h-screen flex items-center justify-center bg-background p-4">
                <Card className="w-full max-w-lg">
                    <CardHeader className="text-center">
                        <div className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-primary/10">
                            <Building2 className="h-6 w-6 text-primary" />
                        </div>
                        <CardTitle className="text-2xl">{trans('panel::panel.tenancy.create_tenant')}</CardTitle>
                        <CardDescription>{trans('panel::panel.tenancy.create_tenant_description')}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-5">
                            {/* Team Name */}
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

                            {/* Subdomain */}
                            <div className="space-y-2">
                                <Label htmlFor="subdomain">{trans('panel::panel.tenancy.subdomain')}</Label>
                                <div className="flex items-center gap-1">
                                    <div className="relative flex-1">
                                        <Input
                                            id="subdomain"
                                            value={form.data.subdomain}
                                            onChange={(event) => {
                                                form.setData('subdomain', event.target.value);
                                                onSubdomainInput();
                                            }}
                                            type="text"
                                            placeholder={trans('panel::panel.tenancy.subdomain_placeholder')}
                                            className="pe-2"
                                            required
                                        />
                                    </div>
                                    <span className="text-sm text-muted-foreground whitespace-nowrap">.{baseDomain}</span>
                                </div>

                                {/* Domain Preview */}
                                <div className="flex items-center gap-2 p-2 rounded-md bg-muted/50">
                                    <Globe className="h-4 w-4 text-muted-foreground shrink-0" />
                                    <span className="text-sm text-muted-foreground truncate">https://{fullDomain}</span>
                                </div>

                                {/* Validation Error */}
                                {subdomainError && (
                                    <div className="flex items-center gap-2 text-destructive text-sm">
                                        <AlertCircle className="h-4 w-4 shrink-0" />
                                        <span>{subdomainError}</span>
                                    </div>
                                )}

                                <InputError message={form.errors.subdomain} />
                            </div>

                            {/* Description (optional) */}
                            <div className="space-y-2">
                                <Label htmlFor="description">
                                    {trans('panel::panel.tenancy.team_description')}{' '}
                                    <span className="text-muted-foreground font-normal">({trans('panel::panel.common.optional')})</span>
                                </Label>
                                <Textarea
                                    id="description"
                                    value={form.data.description}
                                    onChange={(event) => form.setData('description', event.target.value)}
                                    placeholder={trans('panel::panel.tenancy.team_description_placeholder')}
                                    rows={3}
                                />
                                <InputError message={form.errors.description} />
                            </div>

                            {/* Info Box */}
                            <div className="rounded-lg border bg-muted/30 p-4 text-sm space-y-2">
                                <p className="font-medium">{trans('panel::panel.tenancy.what_happens_next')}</p>
                                <ul className="list-disc list-inside text-muted-foreground space-y-1">
                                    <li>{trans('panel::panel.tenancy.provision_database')}</li>
                                    <li>{trans('panel::panel.tenancy.provision_subdomain')}</li>
                                    <li>{trans('panel::panel.tenancy.provision_redirect')}</li>
                                </ul>
                            </div>

                            <Button type="submit" className="w-full" disabled={form.processing || !!subdomainError}>
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
