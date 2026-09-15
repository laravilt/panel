import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState, type ComponentType, type FormEvent, type ReactNode } from 'react';
import { cn } from '@/lib/utils';
import ActionButton from '@laravilt/actions/components/ActionButton';
import OtpResendHook from '@laravilt/auth/components/OtpResendHook';
import SocialLogin from '@laravilt/auth/components/SocialLogin';
import ErrorProvider from '@laravilt/forms/components/ErrorProvider';
import RelationManagers from '@laravilt/panel/components/RelationManagers';
import CardLayout from '@laravilt/panel/layouts/CardLayout';
import PanelLayout from '@laravilt/panel/layouts/PanelLayout';
import SettingsLayout from '@laravilt/panel/layouts/SettingsLayout';
import Schema from '@laravilt/schemas/components/Schema';
import { useLocalization } from '@laravilt/support/composables';
import { resolveComponent } from '@laravilt/support/composables/registry';
import ApiTester from '@laravilt/tables/components/ApiTester';
import Table from '@laravilt/tables/components/Table';

// Hook components map for dynamic rendering
const hookComponents: Record<string, ComponentType<any>> = {
    OtpResendHook,
};

interface BreadcrumbItem {
    label: string;
    url: string | null;
}

interface NavigationItem {
    title: string;
    href: string;
    icon?: string;
    active?: boolean;
}

interface PageData {
    heading: string;
    subheading?: string | null;
    headerActions: any[];
    actionUrl?: string;
}

type HookDefinition = string | { component: string; props?: Record<string, any> } | null;

type ViewName = 'table' | 'grid' | 'api';

/** The part of the Schema ref handle this page uses. */
interface SchemaRefHandle {
    getFormData?: () => Record<string, any>;
}

export interface PageProps {
    page: PageData;
    content?: string | null;
    pageSlug?: string;
    panelId?: string;
    schema?: any[];
    layout?: 'panel' | 'card' | 'simple' | 'full' | 'settings';
    formAction?: string;
    formController?: string;
    clusterNavigation?: NavigationItem[];
    clusterTitle?: string;
    clusterDescription?: string;
    formMethod?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';
    canResetPassword?: boolean;
    canRegister?: boolean;
    canLogin?: boolean;
    resetPasswordUrl?: string;
    registerUrl?: string;
    loginUrl?: string;
    socialProviders?: any[];
    socialRedirectUrl?: string;
    status?: string;
    hasTwoFactorRecovery?: boolean;
    recoveryUrl?: string;
    hasPasskeys?: boolean;
    passkeyLoginOptionsUrl?: string;
    passkeyLoginUrl?: string;
    hasMagicLinks?: boolean;
    magicLinkSendUrl?: string;
    breadcrumbs?: BreadcrumbItem[];
    topHook?: HookDefinition;
    bottomHook?: HookDefinition;
    hasViewToggle?: boolean;
    hasGridOption?: boolean;
    hasApiOption?: boolean;
    availableViews?: string[];
    currentView?: ViewName;
    apiResource?: any;
    apiToken?: string | null;
    record?: any;
    relationManagers?: any[];
    resourceSlug?: string;
    /** Vue default slot. */
    children?: ReactNode;
}

const authLinkClass =
    'text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500';

// Helper functions for base64url encoding/decoding
function base64urlDecode(base64url: string): ArrayBuffer {
    const base64 = base64url.replace(/-/g, '+').replace(/_/g, '/');
    const padded = base64.padEnd(base64.length + ((4 - (base64.length % 4)) % 4), '=');
    const binary = atob(padded);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) {
        bytes[i] = binary.charCodeAt(i);
    }
    return bytes.buffer;
}

function arrayBufferToBase64url(buffer: ArrayBuffer): string {
    const bytes = new Uint8Array(buffer);
    let binary = '';
    for (let i = 0; i < bytes.byteLength; i++) {
        binary += String.fromCharCode(bytes[i]);
    }
    const base64 = btoa(binary);
    return base64.replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
}

export default function Page(props: PageProps) {
    const {
        page,
        content,
        pageSlug,
        panelId,
        schema,
        layout,
        formAction,
        formController,
        clusterNavigation,
        clusterTitle,
        clusterDescription,
        formMethod,
        canRegister,
        canLogin,
        registerUrl,
        loginUrl,
        socialProviders,
        socialRedirectUrl,
        status,
        hasTwoFactorRecovery,
        recoveryUrl,
        hasPasskeys,
        passkeyLoginOptionsUrl,
        passkeyLoginUrl,
        hasMagicLinks,
        magicLinkSendUrl,
        topHook,
        bottomHook,
        hasViewToggle,
        availableViews,
        currentView,
        apiResource,
        apiToken,
        record,
        relationManagers,
        resourceSlug,
        children,
    } = props;

    const { trans } = useLocalization();
    const inertiaPage = usePage();
    const pageErrors = (inertiaPage.props.errors || {}) as Record<string, any>;

    // View toggle functionality - saves preference to localStorage per resource
    const getViewStorageKey = () => {
        // Use pageSlug to create a unique key per resource
        return `laravilt_view_${pageSlug || 'default'}`;
    };

    // Available views with proper default
    const computedAvailableViews: string[] = availableViews || ['table', 'grid'];

    const getSavedView = (): ViewName | null => {
        if (typeof window === 'undefined') return null;
        const saved = localStorage.getItem(getViewStorageKey());
        if (saved && computedAvailableViews.includes(saved)) {
            return saved as ViewName;
        }
        return null;
    };

    const saveViewPreference = (view: ViewName) => {
        if (typeof window === 'undefined') return;
        localStorage.setItem(getViewStorageKey(), view);
    };

    const toggleView = (view: ViewName) => {
        // Save preference to localStorage
        saveViewPreference(view);

        const url = new URL(window.location.href);
        url.searchParams.set('view', view);
        router.visit(url.toString(), { preserveState: true, preserveScroll: true });
    };

    // Whether we need to check/redirect for saved view preference
    // This is true ONLY when we need to redirect to saved preference
    const needsViewCheck = ((): boolean => {
        if (!hasViewToggle) return false;
        if (typeof window === 'undefined') return false;

        const urlParams = new URLSearchParams(window.location.search);
        const urlView = urlParams.get('view');

        // If URL already has a view param, no check needed
        if (urlView) return false;

        // If page > 1, it's infinite scroll, no check needed
        const pageParam = urlParams.get('page');
        if (pageParam && parseInt(pageParam) > 1) return false;

        // Check if saved view differs from current
        const saved = localStorage.getItem(`laravilt_view_${pageSlug || 'default'}`);
        if (saved && computedAvailableViews.includes(saved)) {
            return saved !== (currentView || 'table');
        }

        return false;
    })();

    // Check if current view is API
    const isApiView = currentView === 'api';

    // Check if we have relation managers to display
    const hasRelationManagers = Boolean(relationManagers && relationManagers.length > 0 && record && resourceSlug);

    // Redirect to saved view preference on mount if needed
    const checkSavedViewPreference = () => {
        if (needsViewCheck) {
            const savedView = getSavedView();
            if (savedView) {
                const url = new URL(window.location.href);
                url.searchParams.set('view', savedView);
                router.visit(url.toString(), { preserveState: true, preserveScroll: true, replace: true });
            }
        }
    };

    const contentRef = useRef<HTMLDivElement | null>(null);

    // Schema handle(s): a single handle, or an array of handles when Schemas are rendered in a loop (Vue v-for ref).
    const formRendererRef = useRef<any>(null);
    const formRendererList = useRef<Array<SchemaRefHandle | null>>([]);
    const formRendererListCallbacks = useRef<Map<number, (instance: SchemaRefHandle | null) => void>>(new Map());

    const bindFormRenderer = useCallback((instance: SchemaRefHandle | null) => {
        formRendererRef.current = instance;
    }, []);

    const bindFormRendererAt = (index: number) => {
        let callback = formRendererListCallbacks.current.get(index);

        if (!callback) {
            callback = (instance: SchemaRefHandle | null) => {
                formRendererList.current[index] = instance;
                formRendererRef.current = formRendererList.current.filter(
                    (renderer): renderer is SchemaRefHandle => renderer != null,
                );
            };
            formRendererListCallbacks.current.set(index, callback);
        }

        return callback;
    };

    // Page loading state for smooth transitions
    const [isPageLoading, setIsPageLoading] = useState(true);
    const isPageMounted = useRef(false);

    // Helper to get form data from formRendererRef (handles array case from v-for)
    const getFormRendererData = (): Record<string, any> => {
        const renderer = formRendererRef.current;

        if (!renderer) {
            return {};
        }

        // If it's an array (from v-for), get data from all renderers and merge
        if (Array.isArray(renderer)) {
            let mergedData = {};
            for (const item of renderer) {
                if (item?.getFormData) {
                    mergedData = { ...mergedData, ...item.getFormData() };
                }
            }
            return mergedData;
        }

        // Single renderer
        if (renderer?.getFormData) {
            return renderer.getFormData();
        }

        return {};
    };

    // Use breadcrumbs from props (backend) or fallback to simple default
    const breadcrumbs: BreadcrumbItem[] =
        props.breadcrumbs && props.breadcrumbs.length > 0
            ? props.breadcrumbs
            : [
                  // Fallback: simple Dashboard → Current Page
                  {
                      label: 'Dashboard',
                      url: `/${panelId}`,
                  },
                  {
                      label: page.heading,
                      url: null,
                  },
              ];

    // Merge actionUrl into each action (only if action doesn't have its own)
    const actionsWithUrl: any[] = !page.headerActions
        ? []
        : page.headerActions.map((action) => ({
              ...action,
              actionUrl: action.actionUrl || page.actionUrl,
          }));

    // Use action route if actions are available, otherwise use formAction
    const computedFormAction = actionsWithUrl && actionsWithUrl.length > 0 ? undefined : formAction;

    // Extract actions from schema (actions are embedded in form schemas for Create/Edit pages)
    const schemaActions: any[] = (() => {
        if (!schema || !Array.isArray(schema)) return [];

        const actions: any[] = [];

        for (const item of schema) {
            // Check if this item is a schema container (form)
            if (item.fields || item.schema) {
                const schemaFields = item.schema || item.fields || [];
                // Find action objects in the schema
                for (const field of schemaFields) {
                    if (field.hasAction === true || (field.name && !field.component)) {
                        actions.push({
                            ...field,
                            actionUrl: field.actionUrl || page.actionUrl,
                        });
                    }
                }
            }
        }

        return actions;
    })();

    // Check if schema contains form fields (not just Grid/Table/InfoList)
    const hasFormSchema = (() => {
        if (!schema || !Array.isArray(schema)) return false;

        // Check if we have schema-embedded actions (indicates it's a form page like Create/Edit)
        if (schemaActions.length === 0) return false;

        // Check if schema has form fields (fields or schema property, but not columns/card which are Grid/Table)
        return schema.some((item: any) => {
            const hasFields = item.fields || item.schema;
            const isGridOrTable = item.columns || item.card;
            return hasFields && !isGridOrTable;
        });
    })();

    // Check if schema has nested wrapper objects (Schema containers with fields/schema property)
    // vs flat form components (TextInput, Checkbox, etc. directly in schema array)
    const hasNestedSchemaWrappers = !schema || !Array.isArray(schema) ? false : schema.some((item: any) => item.fields || item.schema);

    // Check if schema contains InfoList (View pages - should not have internal scroll)
    const hasInfoListSchema = (() => {
        if (!schema || !Array.isArray(schema)) return false;

        // InfoList pages have fields/schema but NO actions (no submit button)
        // Unlike form pages which have schemaActions
        return (
            schema.some((item: any) => {
                const hasFields = item.fields || item.schema;
                const isGridOrTable = item.columns || item.card;
                return hasFields && !isGridOrTable;
            }) && schemaActions.length === 0
        );
    })();

    // Determine if page should use internal scroll (only for Grid/Table views, not forms or infolists)
    const shouldUseInternalScroll = !hasFormSchema && !hasInfoListSchema;

    // Transform breadcrumbs to frontend format (label/url → title/href)
    const transformedBreadcrumbs = breadcrumbs.map((item) => ({
        title: item.label,
        href: item.url || '#',
    }));

    const handleFormSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        // Step 1: Get the first action
        const action = actionsWithUrl[0];
        if (!action || !action.actionUrl) {
            console.error('No action found for form submission');
            return;
        }

        // Step 2: Get form data from FormRenderer
        let data: Record<string, any> = {};

        if (formRendererRef.current && typeof formRendererRef.current.getFormData === 'function') {
            data = formRendererRef.current.getFormData();
        } else {
            console.error('FormRenderer ref not available or getFormData method not found');
            return;
        }

        // Step 3: Add action token
        if (action.actionToken) {
            data.token = action.actionToken;
        } else {
            data.action = action.name;
        }

        // Step 4: Submit via Inertia - Backend validation will run in the action closure
        router.post(action.actionUrl, data, {
            preserveState: (visitPage) => {
                // Preserve state if there are errors (for validation)
                return Object.keys(visitPage.props.errors || {}).length > 0;
            },
            preserveScroll: true,
            onError: (errors) => {
                console.error('Validation errors received:', errors);
            },
            onSuccess: () => {
                // Action executed successfully
            },
        });
    };

    // Passkey login handler
    const handlePasskeyLogin = async () => {
        if (!passkeyLoginOptionsUrl || !passkeyLoginUrl) {
            return;
        }

        try {
            // Get WebAuthn assertion options from server
            const optionsResponse = await fetch(passkeyLoginOptionsUrl, {
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                },
            });

            if (!optionsResponse.ok) {
                throw new Error(`Failed to fetch WebAuthn options: ${optionsResponse.status}`);
            }

            const options = await optionsResponse.json();

            // Convert base64url strings to ArrayBuffer
            options.challenge = base64urlDecode(options.challenge);
            if (options.allowCredentials) {
                options.allowCredentials = options.allowCredentials.map((cred: any) => ({
                    ...cred,
                    id: base64urlDecode(cred.id),
                }));
            }

            // Get credential using WebAuthn API
            const credential = (await navigator.credentials.get({
                publicKey: options,
            })) as PublicKeyCredential;

            if (!credential) {
                throw new Error('No credential received');
            }

            // Prepare assertion data for server
            const assertionResponse = credential.response as AuthenticatorAssertionResponse;
            const assertionData = {
                id: credential.id,
                rawId: arrayBufferToBase64url(credential.rawId),
                type: credential.type,
                response: {
                    clientDataJSON: arrayBufferToBase64url(assertionResponse.clientDataJSON),
                    authenticatorData: arrayBufferToBase64url(assertionResponse.authenticatorData),
                    signature: arrayBufferToBase64url(assertionResponse.signature),
                    userHandle: assertionResponse.userHandle ? arrayBufferToBase64url(assertionResponse.userHandle) : null,
                },
            };

            // Send assertion to server
            const response = await fetch(passkeyLoginUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify(assertionData),
            });

            if (!response.ok) {
                throw new Error(`Login failed: ${response.status}`);
            }

            const result = await response.json();

            // Redirect to dashboard
            if (result.redirect) {
                window.location.href = result.redirect;
            }
        } catch (error) {
            console.error('Passkey login failed:', error);
            alert('Failed to login with passkey: ' + (error as Error).message);
        }
    };

    // Magic link handler
    const [sendingMagicLink, setSendingMagicLink] = useState(false);
    const sendingMagicLinkRef = useRef(false);

    const handleSendMagicLink = async () => {
        if (!magicLinkSendUrl) {
            return;
        }

        if (sendingMagicLinkRef.current) {
            return;
        }

        sendingMagicLinkRef.current = true;
        setSendingMagicLink(true);

        try {
            const response = await fetch(magicLinkSendUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
            });

            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.error || `Failed to send magic link: ${response.status}`);
            }

            await response.json();
            alert('Magic link sent! Check your email.');
        } catch (error) {
            console.error('Failed to send magic link:', error);
            alert('Failed to send magic link: ' + (error as Error).message);
        } finally {
            sendingMagicLinkRef.current = false;
            setSendingMagicLink(false);
        }
    };

    useEffect(() => {
        // Check if we should redirect to saved view preference
        checkSavedViewPreference();

        // Mark page as mounted and hide loading after a short delay
        isPageMounted.current = true;
        const timer = setTimeout(() => {
            setIsPageLoading(false);
        }, 100);

        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    // Top Hook (Vue `<component :is="topHook.component">` resolves globally registered components)
    const renderTopHook = (): ReactNode => {
        if (topHook && typeof topHook === 'object') {
            const HookComponent = resolveComponent(topHook.component);
            return HookComponent ? <HookComponent {...(topHook.props || {})} className="mb-6" /> : null;
        }

        if (topHook && typeof topHook === 'string') {
            return <div dangerouslySetInnerHTML={{ __html: topHook }} className="mb-6" />;
        }

        return null;
    };

    // Bottom Hook
    const renderBottomHook = (): ReactNode => {
        if (bottomHook && typeof bottomHook === 'object' && hookComponents[bottomHook.component]) {
            const HookComponent = hookComponents[bottomHook.component];
            return <HookComponent {...(bottomHook.props || {})} className="mt-6" />;
        }

        if (bottomHook && typeof bottomHook === 'string') {
            return <div dangerouslySetInnerHTML={{ __html: bottomHook }} className="mt-6" />;
        }

        return null;
    };

    // Status Message
    const renderStatus = (): ReactNode =>
        status ? (
            <div className="mb-4 rounded-md bg-green-50 p-4 text-sm font-medium text-green-600 dark:bg-green-950 dark:text-green-400">
                {status}
            </div>
        ) : null;

    const viewToggleButtonClass = (view: ViewName) =>
        cn(
            'inline-flex items-center justify-center rounded px-3 py-1.5 text-sm font-medium transition-colors',
            currentView === view ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground',
        );

    const renderSettingsContent = (): ReactNode => (
        <section className="max-w-xl space-y-12">
            <div className="flex flex-col space-y-6">
                <header>
                    <h3 className="mb-0.5 text-base font-medium">{page.heading}</h3>
                    {page.subheading && <p className="text-sm text-muted-foreground">{page.subheading}</p>}
                </header>

                {content ? (
                    // Render Blade view content if provided
                    <div ref={contentRef} dangerouslySetInnerHTML={{ __html: content }} />
                ) : schema && schema.length ? (
                    // Render Schema Form if available
                    <div>
                        {renderStatus()}

                        {renderTopHook()}

                        {actionsWithUrl && actionsWithUrl.length ? (
                            // Use regular form when actions are present (ActionButton handles submission)
                            <form onSubmit={handleFormSubmit} className="space-y-6">
                                <ErrorProvider errors={pageErrors}>
                                    {/* Render Schema */}
                                    <Schema ref={bindFormRenderer} schema={schema} formController={formController} />

                                    {/* Submit Actions */}
                                    <div className="flex items-center gap-4">
                                        {actionsWithUrl.map((action) => (
                                            <ActionButton
                                                key={action.name}
                                                {...action}
                                                getFormData={() => formRendererRef.current?.getFormData()}
                                            />
                                        ))}
                                    </div>
                                </ErrorProvider>
                            </form>
                        ) : (
                            // Use Inertia Form when no actions (traditional form submission)
                            <Form action={computedFormAction} method={formMethod} className="space-y-6">
                                {({ errors }) => (
                                    <ErrorProvider errors={errors as Record<string, any>}>
                                        {/* Render Schema */}
                                        <Schema schema={schema} formController={formController} />
                                    </ErrorProvider>
                                )}
                            </Form>
                        )}

                        {renderBottomHook()}
                    </div>
                ) : (
                    children
                )}
            </div>
        </section>
    );

    const renderPanelContent = (): ReactNode => (
        <div
            className={cn(
                'flex flex-1 flex-col gap-4 p-4',
                shouldUseInternalScroll ? 'min-h-0 overflow-hidden max-h-[calc(100vh-4rem)]' : '',
            )}
        >
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 flex-shrink-0">
                <div>
                    <h1 className="text-2xl font-semibold">{page.heading}</h1>
                    {page.subheading && <p className="mt-1 text-sm text-muted-foreground">{page.subheading}</p>}
                </div>

                {/* Header Actions */}
                <div className="flex flex-wrap items-center gap-2">
                    {/* View Toggle (Table/Grid/API) */}
                    {hasViewToggle && (
                        <div className="flex items-center rounded-md border bg-muted p-1" data-view-toggle="">
                            {/* Table View Button (only if table view is available) */}
                            {computedAvailableViews.includes('table') && (
                                <button
                                    type="button"
                                    onClick={() => toggleView('table')}
                                    className={viewToggleButtonClass('table')}
                                    data-view-toggle-table=""
                                    title="Table View"
                                >
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        className="h-4 w-4"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        strokeWidth="2"
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                    >
                                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2" />
                                        <line x1="3" y1="9" x2="21" y2="9" />
                                        <line x1="3" y1="15" x2="21" y2="15" />
                                        <line x1="9" y1="3" x2="9" y2="21" />
                                    </svg>
                                </button>
                            )}
                            {/* Grid View Button (only if grid view is available) */}
                            {computedAvailableViews.includes('grid') && (
                                <button
                                    type="button"
                                    onClick={() => toggleView('grid')}
                                    className={viewToggleButtonClass('grid')}
                                    data-view-toggle-grid=""
                                    title="Grid View"
                                >
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        className="h-4 w-4"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        strokeWidth="2"
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                    >
                                        <rect x="3" y="3" width="7" height="7" />
                                        <rect x="14" y="3" width="7" height="7" />
                                        <rect x="14" y="14" width="7" height="7" />
                                        <rect x="3" y="14" width="7" height="7" />
                                    </svg>
                                </button>
                            )}
                            {/* API View Button (only if API option is available) */}
                            {computedAvailableViews.includes('api') && (
                                <button
                                    type="button"
                                    onClick={() => toggleView('api')}
                                    className={viewToggleButtonClass('api')}
                                    data-view-toggle-api=""
                                    title="API Tester"
                                >
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        className="h-4 w-4"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        strokeWidth="2"
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                    >
                                        <path d="M4 4h6v6H4z" />
                                        <path d="M14 4h6v6h-6z" />
                                        <path d="M4 14h6v6H4z" />
                                        <path d="M17 14v3a2 2 0 0 1-2 2h-3" />
                                        <path d="M14 17l3-3 3 3" />
                                    </svg>
                                </button>
                            )}
                        </div>
                    )}

                    {/* Action Buttons */}
                    {actionsWithUrl &&
                        actionsWithUrl.length > 0 &&
                        actionsWithUrl.map((action) => <ActionButton key={action.name} {...action} />)}
                </div>
            </div>

            {/* Page Content Area */}
            <div className="flex-1 min-h-0 flex flex-col">
                {content ? (
                    // Render Blade view content if provided
                    <div ref={contentRef} dangerouslySetInnerHTML={{ __html: content }} />
                ) : schema && schema.length ? (
                    // Render Schema (Table/Grid/Form/InfoList) if available
                    <div className={cn('flex-1 flex flex-col', shouldUseInternalScroll ? 'min-h-0 overflow-y-auto' : '')}>
                        <ErrorProvider errors={pageErrors}>
                            {hasFormSchema ? (
                                // Form Schema (Create/Edit pages)
                                isPageLoading ? (
                                    // Form Skeleton while loading
                                    <div className="space-y-6 pb-6 animate-in fade-in duration-150">
                                        <div className="bg-card rounded-xl border shadow-sm p-6 space-y-6">
                                            {/* Section header skeleton */}
                                            <div className="flex items-center gap-3 pb-4 border-b">
                                                <div className="h-10 w-10 bg-muted/60 rounded-lg animate-pulse"></div>
                                                <div className="space-y-2 flex-1">
                                                    <div className="h-4 bg-muted/60 rounded w-1/4 animate-pulse" style={{ animationDelay: '0ms' }}></div>
                                                    <div className="h-3 bg-muted/60 rounded w-1/3 animate-pulse" style={{ animationDelay: '50ms' }}></div>
                                                </div>
                                            </div>
                                            {/* Form fields skeleton */}
                                            <div className="space-y-4">
                                                <div className="space-y-2">
                                                    <div className="h-4 bg-muted/60 rounded w-20 animate-pulse" style={{ animationDelay: '75ms' }}></div>
                                                    <div className="h-10 bg-muted/60 rounded animate-pulse" style={{ animationDelay: '100ms' }}></div>
                                                </div>
                                                <div className="space-y-2">
                                                    <div className="h-4 bg-muted/60 rounded w-24 animate-pulse" style={{ animationDelay: '125ms' }}></div>
                                                    <div className="h-10 bg-muted/60 rounded animate-pulse" style={{ animationDelay: '150ms' }}></div>
                                                </div>
                                                <div className="space-y-2">
                                                    <div className="h-4 bg-muted/60 rounded w-16 animate-pulse" style={{ animationDelay: '175ms' }}></div>
                                                    <div className="h-24 bg-muted/60 rounded animate-pulse" style={{ animationDelay: '200ms' }}></div>
                                                </div>
                                            </div>
                                        </div>
                                        {/* Action button skeleton */}
                                        <div className="h-10 bg-muted/60 rounded w-32 animate-pulse" style={{ animationDelay: '225ms' }}></div>
                                    </div>
                                ) : (
                                    // Actual Form
                                    <form onSubmit={handleFormSubmit} className="space-y-6 pb-6 animate-in fade-in duration-200">
                                        {schema.map((item: any, index: number) => (
                                            <div key={index}>
                                                {/* Render Schema (Form) - skip actions, they're rendered separately */}
                                                {item.fields || item.schema ? (
                                                    <Schema
                                                        ref={bindFormRendererAt(index)}
                                                        schema={item.schema || item.fields || []}
                                                        parentHandlesActions={true}
                                                        formController={formController}
                                                    />
                                                ) : null}
                                            </div>
                                        ))}

                                        {/* Submit Actions for form */}
                                        {schemaActions && schemaActions.length > 0 && (
                                            <div className="flex items-center gap-4">
                                                {schemaActions.map((action) => (
                                                    <ActionButton key={action.name} {...action} getFormData={getFormRendererData} />
                                                ))}
                                            </div>
                                        )}

                                        {/* Relation Managers (for Edit pages with Form) */}
                                        {hasRelationManagers && (
                                            <RelationManagers
                                                relationManagers={relationManagers || []}
                                                ownerRecordId={record?.id}
                                                resourceSlug={resourceSlug || ''}
                                                panelId={panelId || ''}
                                            />
                                        )}
                                    </form>
                                )
                            ) : // Non-form schema (Grid/Table/InfoList/API)
                            hasInfoListSchema && isPageLoading ? (
                                // InfoList/View Skeleton while loading
                                <div className="space-y-6 animate-in fade-in duration-150">
                                    <div className="bg-card rounded-xl border shadow-sm">
                                        {/* Section header skeleton */}
                                        <div className="flex items-center gap-3 p-6 border-b">
                                            <div className="h-10 w-10 bg-muted/60 rounded-lg animate-pulse"></div>
                                            <div className="space-y-2 flex-1">
                                                <div className="h-4 bg-muted/60 rounded w-1/4 animate-pulse" style={{ animationDelay: '0ms' }}></div>
                                                <div className="h-3 bg-muted/60 rounded w-1/3 animate-pulse" style={{ animationDelay: '50ms' }}></div>
                                            </div>
                                        </div>
                                        {/* InfoList entries skeleton */}
                                        <div className="p-6 space-y-4">
                                            <div className="grid grid-cols-2 gap-4">
                                                <div className="space-y-2">
                                                    <div className="h-3 bg-muted/60 rounded w-16 animate-pulse" style={{ animationDelay: '75ms' }}></div>
                                                    <div className="h-5 bg-muted/60 rounded w-3/4 animate-pulse" style={{ animationDelay: '100ms' }}></div>
                                                </div>
                                                <div className="space-y-2">
                                                    <div className="h-3 bg-muted/60 rounded w-20 animate-pulse" style={{ animationDelay: '125ms' }}></div>
                                                    <div className="h-5 bg-muted/60 rounded w-2/3 animate-pulse" style={{ animationDelay: '150ms' }}></div>
                                                </div>
                                                <div className="space-y-2">
                                                    <div className="h-3 bg-muted/60 rounded w-14 animate-pulse" style={{ animationDelay: '175ms' }}></div>
                                                    <div className="h-5 bg-muted/60 rounded w-1/2 animate-pulse" style={{ animationDelay: '200ms' }}></div>
                                                </div>
                                                <div className="space-y-2">
                                                    <div className="h-3 bg-muted/60 rounded w-18 animate-pulse" style={{ animationDelay: '225ms' }}></div>
                                                    <div className="h-5 bg-muted/60 rounded w-full animate-pulse" style={{ animationDelay: '250ms' }}></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            ) : needsViewCheck ? (
                                // View redirect skeleton
                                <div className="space-y-4 animate-in fade-in duration-150">
                                    <div className="h-12 bg-muted/60 rounded-lg animate-pulse"></div>
                                    <div className="h-64 bg-muted/60 rounded-lg animate-pulse" style={{ animationDelay: '100ms' }}></div>
                                </div>
                            ) : isApiView && apiResource ? (
                                // API View - Render ApiTester component
                                <div className="h-full flex flex-col animate-in fade-in duration-200">
                                    <ApiTester apiResource={apiResource} apiToken={apiToken} />
                                </div>
                            ) : (
                                // Actual content (Table/InfoList)
                                <div className="h-full flex flex-col space-y-6 animate-in fade-in duration-200">
                                    {schema.map((item: any, index: number) => (
                                        <div key={index} className="h-full flex flex-col">
                                            {item.columns ? (
                                                // Render Table (handles both table and grid views based on currentView)
                                                <Table
                                                    table={item}
                                                    records={item.records || []}
                                                    pagination={item.pagination}
                                                    recordActions={item.recordActions || []}
                                                    bulkActions={item.bulkActions || []}
                                                    filterIndicators={item.filterIndicators || []}
                                                    resourceSlug={item.resourceSlug || ''}
                                                    queryRoute={item.queryRoute || ''}
                                                    currentView={(currentView || 'table') as 'table' | 'grid'}
                                                />
                                            ) : item.fields || item.schema ? (
                                                // Render Schema (InfoList - no actions)
                                                <Schema schema={item.schema || item.fields || []} formController={formController} />
                                            ) : (
                                                // Fallback for unknown types
                                                <div>
                                                    <pre>{JSON.stringify(item, null, 2)}</pre>
                                                </div>
                                            )}
                                        </div>
                                    ))}

                                    {/* Relation Managers (for View/Edit pages with InfoList) */}
                                    {hasRelationManagers && hasInfoListSchema && (
                                        <RelationManagers
                                            relationManagers={relationManagers || []}
                                            ownerRecordId={record?.id}
                                            resourceSlug={resourceSlug || ''}
                                            panelId={panelId || ''}
                                        />
                                    )}
                                </div>
                            )}
                        </ErrorProvider>
                    </div>
                ) : (
                    // Slot for programmatic content
                    (children ?? (
                        // Default empty state for custom pages
                        <div className="flex h-full items-center justify-center rounded-lg border border-dashed p-8">
                            <div className="text-center">
                                <h3 className="text-lg font-semibold">Custom Page Content</h3>
                                <p className="mt-2 text-sm text-muted-foreground">
                                    This is a standalone page. You can add custom components and content here.
                                </p>
                            </div>
                        </div>
                    ))
                )}
            </div>
        </div>
    );

    // Non-panel layouts (like AuthCard) render content directly
    const renderDefaultContent = (): ReactNode => {
        if (content) {
            return <div ref={contentRef} dangerouslySetInnerHTML={{ __html: content }} />;
        }

        if (!(schema && schema.length)) {
            return children;
        }

        // Render Schema Form if available
        return (
            <div>
                {renderStatus()}

                {renderTopHook()}

                {actionsWithUrl && actionsWithUrl.length ? (
                    // Use regular form when actions are present (ActionButton handles submission)
                    <form onSubmit={handleFormSubmit} className="flex flex-col gap-6">
                        <ErrorProvider errors={pageErrors}>
                            {/* Render Schema - handle both nested wrappers and flat form components */}
                            {hasNestedSchemaWrappers ? (
                                // Nested schema wrappers (e.g., RegisterTenant with Schema container)
                                schema.map((item: any, index: number) =>
                                    item.fields || item.schema ? (
                                        <Schema
                                            key={index}
                                            ref={bindFormRendererAt(index)}
                                            schema={item.schema || item.fields || []}
                                            formController={formController}
                                        />
                                    ) : null,
                                )
                            ) : (
                                // Flat form components (e.g., Login page)
                                <Schema ref={bindFormRenderer} schema={schema} formController={formController} />
                            )}

                            {/* Submit Actions */}
                            <div className="flex flex-col gap-4">
                                {actionsWithUrl.map((action) => (
                                    <ActionButton key={action.name} {...action} getFormData={getFormRendererData} />
                                ))}
                            </div>

                            {/* Social Login */}
                            {socialProviders && socialProviders.length > 0 && (
                                <SocialLogin providers={socialProviders} redirectUrl={socialRedirectUrl}>
                                    {trans('laravilt-auth::auth.social.or_continue_with')}
                                </SocialLogin>
                            )}

                            {/* Auth Footer Links */}
                            {canRegister && registerUrl && (
                                <div className="text-center text-sm text-muted-foreground">
                                    {trans('laravilt-auth::auth.login.no_account')}{' '}
                                    <Link href={registerUrl} className={authLinkClass}>
                                        {trans('laravilt-auth::auth.login.sign_up')}
                                    </Link>
                                </div>
                            )}

                            {canLogin && loginUrl && (
                                <div className="text-center text-sm text-muted-foreground">
                                    {trans('laravilt-auth::auth.register.have_account')}{' '}
                                    <Link href={loginUrl} className={authLinkClass}>
                                        {trans('laravilt-auth::auth.register.sign_in')}
                                    </Link>
                                </div>
                            )}

                            {/* Two-Factor Recovery Code Link */}
                            {hasTwoFactorRecovery && recoveryUrl && (
                                <div className="text-center text-sm text-muted-foreground">
                                    {trans('laravilt-auth::auth.two_factor_challenge.lost_device')}{' '}
                                    <Link href={recoveryUrl} className={authLinkClass}>
                                        {trans('laravilt-auth::auth.two_factor_challenge.use_recovery')}
                                    </Link>
                                </div>
                            )}

                            {/* Passkey Authentication Option */}
                            {hasPasskeys && passkeyLoginOptionsUrl && passkeyLoginUrl && (
                                <div className="text-center text-sm text-muted-foreground">
                                    {trans('laravilt-auth::auth.common.or')}{' '}
                                    <button type="button" onClick={handlePasskeyLogin} className={authLinkClass}>
                                        {trans('laravilt-auth::auth.login.use_passkey')}
                                    </button>
                                </div>
                            )}

                            {/* Magic Link Authentication Option */}
                            {hasMagicLinks && magicLinkSendUrl && (
                                <div className="text-center text-sm text-muted-foreground">
                                    {trans('laravilt-auth::auth.common.or')}{' '}
                                    <button
                                        type="button"
                                        onClick={handleSendMagicLink}
                                        disabled={sendingMagicLink}
                                        className={`${authLinkClass} disabled:opacity-50 disabled:cursor-not-allowed`}
                                    >
                                        {sendingMagicLink
                                            ? trans('laravilt-auth::auth.common.processing')
                                            : trans('laravilt-auth::auth.magic_link.send')}
                                    </button>
                                </div>
                            )}
                        </ErrorProvider>
                    </form>
                ) : (
                    // Use Inertia Form when no actions (traditional form submission)
                    <Form action={computedFormAction} method={formMethod} className="flex flex-col gap-6">
                        {({ errors }) => (
                            <ErrorProvider errors={errors as Record<string, any>}>
                                {/* Render Schema - iterate over schema items and extract inner schema */}
                                {schema.map((item: any, index: number) =>
                                    item.fields || item.schema ? (
                                        <Schema key={index} schema={item.schema || item.fields || []} formController={formController} />
                                    ) : null,
                                )}
                            </ErrorProvider>
                        )}
                    </Form>
                )}

                {renderBottomHook()}
            </div>
        );
    };

    const body: ReactNode =
        layout === 'settings' ? renderSettingsContent() : layout === 'panel' || !layout ? renderPanelContent() : renderDefaultContent();

    return (
        <>
            <Head title={page.heading} />

            {layout === 'card' ? (
                <CardLayout title={page.heading} description={page.subheading ?? undefined}>
                    {body}
                </CardLayout>
            ) : layout === 'settings' ? (
                <SettingsLayout
                    breadcrumbs={transformedBreadcrumbs}
                    navigation={clusterNavigation}
                    title={clusterTitle}
                    description={clusterDescription}
                >
                    {body}
                </SettingsLayout>
            ) : (
                <PanelLayout breadcrumbs={transformedBreadcrumbs}>{body}</PanelLayout>
            )}
        </>
    );
}
