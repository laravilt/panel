<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import ActionButton from '@laravilt/actions/components/ActionButton.vue';
import ErrorProvider from '@laravilt/forms/components/ErrorProvider.vue';
import Schema from '@laravilt/schemas/components/Schema.vue';
import Table from '@laravilt/tables/components/Table.vue';
import ApiTester from '@laravilt/tables/components/ApiTester.vue';
import SocialLogin from '@laravilt/auth/components/SocialLogin.vue';
import OtpResendHook from '@laravilt/auth/components/OtpResendHook.vue';
import RelationManagers from '../../components/RelationManagers.vue';
import { useLocalization } from '@laravilt/support/composables';
import { computed, onMounted, ref, markRaw, watch } from 'vue';

const { trans } = useLocalization();
import CardLayout from '../../layouts/CardLayout.vue';
import PanelLayout from '../../layouts/PanelLayout.vue';
import SettingsLayout from '../../layouts/SettingsLayout.vue';

// Mark layout components as raw to prevent Vue from making them reactive
// This helps with persistent layouts performance
const PanelLayoutRaw = markRaw(PanelLayout);
const CardLayoutRaw = markRaw(CardLayout);
const SettingsLayoutRaw = markRaw(SettingsLayout);

// Hook components map for dynamic rendering
const hookComponents: Record<string, any> = {
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

const props = defineProps<{
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
    topHook?:
        | string
        | { component: string; props?: Record<string, any> }
        | null;
    bottomHook?:
        | string
        | { component: string; props?: Record<string, any> }
        | null;
    hasViewToggle?: boolean;
    hasGridOption?: boolean;
    hasApiOption?: boolean;
    availableViews?: string[];
    currentView?: 'table' | 'grid' | 'api';
    apiResource?: any;
    apiToken?: string | null;
    record?: any;
    relationManagers?: any[];
    resourceSlug?: string;
}>();

// View toggle functionality - saves preference to localStorage per resource
const getViewStorageKey = () => {
    // Use pageSlug to create a unique key per resource
    return `laravilt_view_${props.pageSlug || 'default'}`;
};

const getSavedView = (): 'table' | 'grid' | 'api' | null => {
    if (typeof window === 'undefined') return null;
    const saved = localStorage.getItem(getViewStorageKey());
    const validViews = props.availableViews || ['table', 'grid'];
    if (saved && validViews.includes(saved)) {
        return saved as 'table' | 'grid' | 'api';
    }
    return null;
};

const saveViewPreference = (view: 'table' | 'grid' | 'api') => {
    if (typeof window === 'undefined') return;
    localStorage.setItem(getViewStorageKey(), view);
};

const toggleView = (view: 'table' | 'grid' | 'api') => {
    // Save preference to localStorage
    saveViewPreference(view);

    const url = new URL(window.location.href);
    url.searchParams.set('view', view);
    router.visit(url.toString(), { preserveState: true, preserveScroll: true });
};

// Compute if we need to check/redirect for saved view preference
// This is true ONLY when we need to redirect to saved preference
const needsViewCheck = computed(() => {
    if (!props.hasViewToggle) return false;
    if (typeof window === 'undefined') return false;

    const urlParams = new URLSearchParams(window.location.search);
    const urlView = urlParams.get('view');

    // If URL already has a view param, no check needed
    if (urlView) return false;

    // If page > 1, it's infinite scroll, no check needed
    const pageParam = urlParams.get('page');
    if (pageParam && parseInt(pageParam) > 1) return false;

    // Check if saved view differs from current
    const saved = localStorage.getItem(`laravilt_view_${props.pageSlug || 'default'}`);
    const validViews = props.availableViews || ['table', 'grid'];
    if (saved && validViews.includes(saved)) {
        return saved !== (props.currentView || 'table');
    }

    return false;
});

// Computed to check if current view is API
const isApiView = computed(() => props.currentView === 'api');

// Computed to check if we have relation managers to display
const hasRelationManagers = computed(() => {
    return props.relationManagers && props.relationManagers.length > 0 && props.record && props.resourceSlug;
});

// Redirect to saved view preference on mount if needed
const checkSavedViewPreference = () => {
    if (needsViewCheck.value) {
        const savedView = getSavedView();
        if (savedView) {
            const url = new URL(window.location.href);
            url.searchParams.set('view', savedView);
            router.visit(url.toString(), { preserveState: true, preserveScroll: true, replace: true });
        }
    }
};

const contentRef = ref<HTMLElement | null>(null);
const vueApps = ref<any[]>([]);
const formRendererRef = ref<any>(null);

// Page loading state for smooth transitions
const isPageLoading = ref(true);
const isPageMounted = ref(false);

// Helper to get form data from formRendererRef (handles array case from v-for)
const getFormRendererData = () => {
    if (!formRendererRef.value) {
        return {};
    }

    // If it's an array (from v-for), get data from all renderers and merge
    if (Array.isArray(formRendererRef.value)) {
        let mergedData = {};
        for (const renderer of formRendererRef.value) {
            if (renderer?.getFormData) {
                mergedData = { ...mergedData, ...renderer.getFormData() };
            }
        }
        return mergedData;
    }

    // Single renderer
    if (formRendererRef.value?.getFormData) {
        return formRendererRef.value.getFormData();
    }

    return {};
};

// Use breadcrumbs from props (backend) or fallback to simple default
const breadcrumbs = computed<BreadcrumbItem[]>(() => {
    if (props.breadcrumbs && props.breadcrumbs.length > 0) {
        return props.breadcrumbs;
    }
    // Fallback: simple Dashboard → Current Page
    return [
        {
            label: 'Dashboard',
            url: `/${props.panelId}`,
        },
        {
            label: props.page.heading,
            url: null,
        },
    ];
});

// Merge actionUrl into each action (only if action doesn't have its own)
const actionsWithUrl = computed(() => {
    if (!props.page.headerActions) return [];

    return props.page.headerActions.map(action => ({
        ...action,
        actionUrl: action.actionUrl || props.page.actionUrl,
    }));
});

// Use action route if actions are available, otherwise use formAction
const computedFormAction = computed(() => {
    return actionsWithUrl.value && actionsWithUrl.value.length > 0
        ? undefined
        : props.formAction;
});

// Extract actions from schema (actions are embedded in form schemas for Create/Edit pages)
const schemaActions = computed(() => {
    if (!props.schema || !Array.isArray(props.schema)) return [];

    const actions: any[] = [];

    for (const item of props.schema) {
        // Check if this item is a schema container (form)
        if (item.fields || item.schema) {
            const schemaFields = item.schema || item.fields || [];
            // Find action objects in the schema
            for (const field of schemaFields) {
                if (field.hasAction === true || (field.name && !field.component)) {
                    actions.push({
                        ...field,
                        actionUrl: field.actionUrl || props.page.actionUrl,
                    });
                }
            }
        }
    }

    return actions;
});

// Check if schema contains form fields (not just Grid/Table/InfoList)
const hasFormSchema = computed(() => {
    if (!props.schema || !Array.isArray(props.schema)) return false;

    // Check if we have schema-embedded actions (indicates it's a form page like Create/Edit)
    if (schemaActions.value.length === 0) return false;

    // Check if schema has form fields (fields or schema property, but not columns/card which are Grid/Table)
    return props.schema.some((item: any) => {
        const hasFields = item.fields || item.schema;
        const isGridOrTable = item.columns || item.card;
        return hasFields && !isGridOrTable;
    });
});

// Check if schema contains InfoList (View pages - should not have internal scroll)
const hasInfoListSchema = computed(() => {
    if (!props.schema || !Array.isArray(props.schema)) return false;

    // InfoList pages have fields/schema but NO actions (no submit button)
    // Unlike form pages which have schemaActions
    return props.schema.some((item: any) => {
        const hasFields = item.fields || item.schema;
        const isGridOrTable = item.columns || item.card;
        return hasFields && !isGridOrTable;
    }) && schemaActions.value.length === 0;
});

// Determine if page should use internal scroll (only for Grid/Table views, not forms or infolists)
const shouldUseInternalScroll = computed(() => {
    return !hasFormSchema.value && !hasInfoListSchema.value;
});

const LayoutComponent = computed(() => {
    switch (props.layout) {
        case 'card':
            return CardLayoutRaw;
        case 'settings':
            return SettingsLayoutRaw;
        case 'panel':
        default:
            return PanelLayoutRaw;
    }
});

// Transform breadcrumbs to frontend format (label/url → title/href)
const transformedBreadcrumbs = computed(() => {
    return breadcrumbs.value.map(item => ({
        title: item.label,
        href: item.url || '#',
    }));
});

const layoutProps = computed(() => {
    if (props.layout === 'card') {
        return {
            title: props.page.heading,
            description: props.page.subheading,
        };
    }
    if (props.layout === 'settings') {
        return {
            breadcrumbs: transformedBreadcrumbs.value,
            navigation: props.clusterNavigation,
            title: props.clusterTitle,
            description: props.clusterDescription,
        };
    }
    return {
        breadcrumbs: transformedBreadcrumbs.value,
    };
});

const handleFormSubmit = (event: Event) => {
    event.preventDefault();

    // Step 1: Get the first action
    const action = actionsWithUrl.value[0];
    if (!action || !action.actionUrl) {
        console.error('No action found for form submission');
        return;
    }

    // Step 2: Get form data from FormRenderer
    let data: Record<string, any> = {};

    if (formRendererRef.value && typeof formRendererRef.value.getFormData === 'function') {
        data = formRendererRef.value.getFormData();
    } else {
        console.error('FormRenderer ref not available or getFormData method not found');
        return;
    }

    console.log('📝 Form data collected from FormRenderer:', data);

    // Step 3: Add action token
    if (action.actionToken) {
        data.token = action.actionToken;
    } else {
        data.action = action.name;
    }

    console.log('🔐 Data with token:', data);

    // Step 4: Submit via Inertia - Backend validation will run in the action closure
    router.post(action.actionUrl, data, {
        preserveState: (page) => {
            // Preserve state if there are errors (for validation)
            return Object.keys(page.props.errors || {}).length > 0;
        },
        preserveScroll: true,
        onError: (errors) => {
            console.error('❌ Validation errors received:', errors);
            console.log('📄 Page props errors:', props);
        },
        onSuccess: () => {
            console.log('✅ Action executed successfully');
        },
    });
};

// Passkey login handler
const handlePasskeyLogin = async () => {
    if (!props.passkeyLoginOptionsUrl || !props.passkeyLoginUrl) {
        return;
    }

    try {
        console.log('Starting passkey login...');

        // Get WebAuthn assertion options from server
        const optionsResponse = await fetch(props.passkeyLoginOptionsUrl, {
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
            }
        });

        if (!optionsResponse.ok) {
            const text = await optionsResponse.text();
            console.error('Failed to fetch options:', optionsResponse.status, text);
            throw new Error(`Failed to fetch WebAuthn options: ${optionsResponse.status}`);
        }

        const options = await optionsResponse.json();
        console.log('Received WebAuthn options:', options);

        // Convert base64url strings to ArrayBuffer
        options.challenge = base64urlDecode(options.challenge);
        if (options.allowCredentials) {
            options.allowCredentials = options.allowCredentials.map((cred: any) => ({
                ...cred,
                id: base64urlDecode(cred.id)
            }));
        }

        // Get credential using WebAuthn API
        console.log('Calling navigator.credentials.get...');
        const credential = await navigator.credentials.get({
            publicKey: options
        }) as PublicKeyCredential;

        if (!credential) {
            throw new Error('No credential received');
        }

        console.log('Credential received:', credential.id);

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
                userHandle: assertionResponse.userHandle ? arrayBufferToBase64url(assertionResponse.userHandle) : null
            }
        };

        console.log('Sending assertion to server...');

        // Send assertion to server
        const response = await fetch(props.passkeyLoginUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            body: JSON.stringify(assertionData)
        });

        if (!response.ok) {
            throw new Error(`Login failed: ${response.status}`);
        }

        const result = await response.json();
        console.log('Login successful:', result);

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
const sendingMagicLink = ref(false);
const handleSendMagicLink = async () => {
    if (!props.magicLinkSendUrl) {
        return;
    }

    if (sendingMagicLink.value) {
        return;
    }

    sendingMagicLink.value = true;

    try {
        console.log('Sending magic link...');

        const response = await fetch(props.magicLinkSendUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            }
        });

        if (!response.ok) {
            const errorData = await response.json();
            throw new Error(errorData.error || `Failed to send magic link: ${response.status}`);
        }

        const result = await response.json();
        console.log('Magic link sent:', result);

        alert('Magic link sent! Check your email.');

        // If in debug mode and URL is provided, log it
        if (result.debug_url) {
            console.log('Debug magic link URL:', result.debug_url);
        }

    } catch (error) {
        console.error('Failed to send magic link:', error);
        alert('Failed to send magic link: ' + (error as Error).message);
    } finally {
        sendingMagicLink.value = false;
    }
};

// Helper functions for base64url encoding/decoding
function base64urlDecode(base64url: string): ArrayBuffer {
    const base64 = base64url.replace(/-/g, '+').replace(/_/g, '/');
    const padded = base64.padEnd(base64.length + (4 - base64.length % 4) % 4, '=');
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

onMounted(() => {
    // Check if we should redirect to saved view preference
    checkSavedViewPreference();

    // Mark page as mounted and hide loading after a short delay
    isPageMounted.value = true;
    setTimeout(() => {
        isPageLoading.value = false;
    }, 100);
});
</script>

<template>
    <Head :title="page.heading" />

    <component :is="LayoutComponent" v-bind="layoutProps">
        <template v-if="layout === 'settings'">
            <section class="max-w-xl space-y-12">
                <div class="flex flex-col space-y-6">
                    <header>
                        <h3 class="mb-0.5 text-base font-medium">
                            {{ page.heading }}
                        </h3>
                        <p
                            v-if="page.subheading"
                            class="text-sm text-muted-foreground"
                        >
                            {{ page.subheading }}
                        </p>
                    </header>

                    <!-- Render Blade view content if provided -->
                    <div v-if="content" ref="contentRef" v-html="content" />

                    <!-- Render Schema Form if available -->
                    <div v-else-if="schema && schema.length">
                        <!-- Status Message -->
                        <div
                            v-if="status"
                            class="mb-4 rounded-md bg-green-50 p-4 text-sm font-medium text-green-600 dark:bg-green-950 dark:text-green-400"
                        >
                            {{ status }}
                        </div>

                        <!-- Top Hook -->
                        <component
                            v-if="topHook && typeof topHook === 'object'"
                            :is="topHook.component"
                            v-bind="topHook.props || {}"
                            class="mb-6"
                        />
                        <div
                            v-else-if="topHook && typeof topHook === 'string'"
                            v-html="topHook"
                            class="mb-6"
                        />

                        <!-- Use regular form when actions are present (ActionButton handles submission) -->
                        <form
                            v-if="actionsWithUrl && actionsWithUrl.length"
                            @submit.prevent="handleFormSubmit"
                            class="space-y-6"
                        >
                            <ErrorProvider :errors="$page.props.errors || {}">
                                <!-- Render Schema -->
                                <Schema ref="formRendererRef" :schema="schema" :form-controller="formController" />

                                <!-- Submit Actions -->
                                <div class="flex items-center gap-4">
                                    <ActionButton
                                        v-for="action in actionsWithUrl"
                                        :key="action.name"
                                        v-bind="action"
                                        :getFormData="() => formRendererRef?.getFormData()"
                                    />
                                </div>
                            </ErrorProvider>
                        </form>

                        <!-- Use Inertia Form when no actions (traditional form submission) -->
                        <Form
                            v-else
                            :action="computedFormAction"
                            :method="formMethod"
                            #default="{ errors, processing }"
                            class="space-y-6"
                        >
                            <ErrorProvider :errors="errors">
                                <!-- Render Schema -->
                                <Schema :schema="schema" :form-controller="formController" />
                            </ErrorProvider>
                        </Form>

                        <!-- Bottom Hook -->
                        <component
                            v-if="bottomHook && typeof bottomHook === 'object' && hookComponents[bottomHook.component]"
                            :is="hookComponents[bottomHook.component]"
                            v-bind="bottomHook.props || {}"
                            class="mt-6"
                        />
                        <div
                            v-else-if="bottomHook && typeof bottomHook === 'string'"
                            v-html="bottomHook"
                            class="mt-6"
                        />
                    </div>

                    <slot v-else />
                </div>
            </section>
        </template>

        <template v-else-if="layout === 'panel' || !layout">
            <div :class="[
                'flex flex-1 flex-col gap-4 p-4',
                shouldUseInternalScroll ? 'min-h-0 overflow-hidden max-h-[calc(100vh-4rem)]' : ''
            ]">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 flex-shrink-0">
                    <div>
                        <h1 class="text-2xl font-semibold">
                            {{ page.heading }}
                        </h1>
                        <p
                            v-if="page.subheading"
                            class="mt-1 text-sm text-muted-foreground"
                        >
                            {{ page.subheading }}
                        </p>
                    </div>

                    <!-- Header Actions -->
                    <div class="flex flex-wrap items-center gap-2">
                        <!-- View Toggle (Table/Grid/API) -->
                        <div
                            v-if="hasViewToggle"
                            class="flex items-center rounded-md border bg-muted p-1"
                            data-view-toggle
                        >
                            <!-- Table View Button (always shown) -->
                            <button
                                type="button"
                                @click="toggleView('table')"
                                :class="[
                                    'inline-flex items-center justify-center rounded px-3 py-1.5 text-sm font-medium transition-colors',
                                    currentView === 'table'
                                        ? 'bg-background text-foreground shadow-sm'
                                        : 'text-muted-foreground hover:text-foreground'
                                ]"
                                data-view-toggle-table
                                title="Table View"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                    <line x1="3" y1="9" x2="21" y2="9"/>
                                    <line x1="3" y1="15" x2="21" y2="15"/>
                                    <line x1="9" y1="3" x2="9" y2="21"/>
                                </svg>
                            </button>
                            <!-- Grid View Button (only if grid option is available) -->
                            <button
                                v-if="hasGridOption"
                                type="button"
                                @click="toggleView('grid')"
                                :class="[
                                    'inline-flex items-center justify-center rounded px-3 py-1.5 text-sm font-medium transition-colors',
                                    currentView === 'grid'
                                        ? 'bg-background text-foreground shadow-sm'
                                        : 'text-muted-foreground hover:text-foreground'
                                ]"
                                data-view-toggle-grid
                                title="Grid View"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="3" width="7" height="7"/>
                                    <rect x="14" y="3" width="7" height="7"/>
                                    <rect x="14" y="14" width="7" height="7"/>
                                    <rect x="3" y="14" width="7" height="7"/>
                                </svg>
                            </button>
                            <!-- API View Button (only if API option is available) -->
                            <button
                                v-if="hasApiOption"
                                type="button"
                                @click="toggleView('api')"
                                :class="[
                                    'inline-flex items-center justify-center rounded px-3 py-1.5 text-sm font-medium transition-colors',
                                    currentView === 'api'
                                        ? 'bg-background text-foreground shadow-sm'
                                        : 'text-muted-foreground hover:text-foreground'
                                ]"
                                data-view-toggle-api
                                title="API Tester"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M4 4h6v6H4z"/>
                                    <path d="M14 4h6v6h-6z"/>
                                    <path d="M4 14h6v6H4z"/>
                                    <path d="M17 14v3a2 2 0 0 1-2 2h-3"/>
                                    <path d="M14 17l3-3 3 3"/>
                                </svg>
                            </button>
                        </div>

                        <!-- Action Buttons -->
                        <template v-if="actionsWithUrl && actionsWithUrl.length">
                            <ActionButton
                                v-for="action in actionsWithUrl"
                                :key="action.name"
                                v-bind="action"
                            />
                        </template>
                    </div>
                </div>

                <!-- Page Content Area -->
                <div class="flex-1 min-h-0 flex flex-col">
                    <!-- Render Blade view content if provided -->
                    <div v-if="content" ref="contentRef" v-html="content" />

                    <!-- Render Schema (Table/Grid/Form/InfoList) if available -->
                    <div v-else-if="schema && schema.length" :class="[
                        'flex-1 flex flex-col',
                        shouldUseInternalScroll ? 'min-h-0 overflow-y-auto' : ''
                    ]">
                        <ErrorProvider :errors="$page.props.errors || {}">
                            <!-- Form Schema (Create/Edit pages) -->
                            <template v-if="hasFormSchema">
                                <!-- Form Skeleton while loading -->
                                <div v-if="isPageLoading" class="space-y-6 pb-6 animate-in fade-in duration-150">
                                    <div class="bg-card rounded-xl border shadow-sm p-6 space-y-6">
                                        <!-- Section header skeleton -->
                                        <div class="flex items-center gap-3 pb-4 border-b">
                                            <div class="h-10 w-10 bg-muted/60 rounded-lg animate-pulse"></div>
                                            <div class="space-y-2 flex-1">
                                                <div class="h-4 bg-muted/60 rounded w-1/4 animate-pulse" style="animation-delay: 0ms"></div>
                                                <div class="h-3 bg-muted/60 rounded w-1/3 animate-pulse" style="animation-delay: 50ms"></div>
                                            </div>
                                        </div>
                                        <!-- Form fields skeleton -->
                                        <div class="space-y-4">
                                            <div class="space-y-2">
                                                <div class="h-4 bg-muted/60 rounded w-20 animate-pulse" style="animation-delay: 75ms"></div>
                                                <div class="h-10 bg-muted/60 rounded animate-pulse" style="animation-delay: 100ms"></div>
                                            </div>
                                            <div class="space-y-2">
                                                <div class="h-4 bg-muted/60 rounded w-24 animate-pulse" style="animation-delay: 125ms"></div>
                                                <div class="h-10 bg-muted/60 rounded animate-pulse" style="animation-delay: 150ms"></div>
                                            </div>
                                            <div class="space-y-2">
                                                <div class="h-4 bg-muted/60 rounded w-16 animate-pulse" style="animation-delay: 175ms"></div>
                                                <div class="h-24 bg-muted/60 rounded animate-pulse" style="animation-delay: 200ms"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Action button skeleton -->
                                    <div class="h-10 bg-muted/60 rounded w-32 animate-pulse" style="animation-delay: 225ms"></div>
                                </div>

                                <!-- Actual Form -->
                                <form
                                    v-else
                                    v-cloak
                                    @submit.prevent="handleFormSubmit"
                                    class="space-y-6 pb-6 animate-in fade-in duration-200"
                                >
                                    <div v-for="(item, index) in schema" :key="index">
                                        <!-- Render Schema (Form) - skip actions, they're rendered separately -->
                                        <Schema
                                            v-if="item.fields || item.schema"
                                            ref="formRendererRef"
                                            :schema="item.schema || item.fields || []"
                                            :parent-handles-actions="true"
                                            :form-controller="formController"
                                        />
                                    </div>

                                    <!-- Submit Actions for form -->
                                    <div
                                        v-if="schemaActions && schemaActions.length"
                                        class="flex items-center gap-4"
                                    >
                                        <ActionButton
                                            v-for="action in schemaActions"
                                            :key="action.name"
                                            v-bind="action"
                                            :getFormData="getFormRendererData"
                                        />
                                    </div>

                                    <!-- Relation Managers (for Edit pages with Form) -->
                                    <RelationManagers
                                        v-if="hasRelationManagers"
                                        :relation-managers="relationManagers"
                                        :owner-record-id="record?.id"
                                        :resource-slug="props.resourceSlug"
                                        :panel-id="panelId || ''"
                                    />
                                </form>
                            </template>

                            <!-- Non-form schema (Grid/Table/InfoList/API) -->
                            <template v-else>
                                <!-- InfoList/View Skeleton while loading -->
                                <div v-if="hasInfoListSchema && isPageLoading" class="space-y-6 animate-in fade-in duration-150">
                                    <div class="bg-card rounded-xl border shadow-sm">
                                        <!-- Section header skeleton -->
                                        <div class="flex items-center gap-3 p-6 border-b">
                                            <div class="h-10 w-10 bg-muted/60 rounded-lg animate-pulse"></div>
                                            <div class="space-y-2 flex-1">
                                                <div class="h-4 bg-muted/60 rounded w-1/4 animate-pulse" style="animation-delay: 0ms"></div>
                                                <div class="h-3 bg-muted/60 rounded w-1/3 animate-pulse" style="animation-delay: 50ms"></div>
                                            </div>
                                        </div>
                                        <!-- InfoList entries skeleton -->
                                        <div class="p-6 space-y-4">
                                            <div class="grid grid-cols-2 gap-4">
                                                <div class="space-y-2">
                                                    <div class="h-3 bg-muted/60 rounded w-16 animate-pulse" style="animation-delay: 75ms"></div>
                                                    <div class="h-5 bg-muted/60 rounded w-3/4 animate-pulse" style="animation-delay: 100ms"></div>
                                                </div>
                                                <div class="space-y-2">
                                                    <div class="h-3 bg-muted/60 rounded w-20 animate-pulse" style="animation-delay: 125ms"></div>
                                                    <div class="h-5 bg-muted/60 rounded w-2/3 animate-pulse" style="animation-delay: 150ms"></div>
                                                </div>
                                                <div class="space-y-2">
                                                    <div class="h-3 bg-muted/60 rounded w-14 animate-pulse" style="animation-delay: 175ms"></div>
                                                    <div class="h-5 bg-muted/60 rounded w-1/2 animate-pulse" style="animation-delay: 200ms"></div>
                                                </div>
                                                <div class="space-y-2">
                                                    <div class="h-3 bg-muted/60 rounded w-18 animate-pulse" style="animation-delay: 225ms"></div>
                                                    <div class="h-5 bg-muted/60 rounded w-full animate-pulse" style="animation-delay: 250ms"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- View redirect skeleton -->
                                <div v-else-if="needsViewCheck" class="space-y-4 animate-in fade-in duration-150">
                                    <div class="h-12 bg-muted/60 rounded-lg animate-pulse"></div>
                                    <div class="h-64 bg-muted/60 rounded-lg animate-pulse" style="animation-delay: 100ms"></div>
                                </div>

                                <!-- API View - Render ApiTester component -->
                                <div v-else-if="isApiView && apiResource" class="h-full flex flex-col animate-in fade-in duration-200">
                                    <ApiTester :api-resource="apiResource" :api-token="apiToken" />
                                </div>

                                <!-- Actual content (Table/InfoList) -->
                                <div v-else v-cloak class="h-full flex flex-col space-y-6 animate-in fade-in duration-200">
                                    <div v-for="(item, index) in schema" :key="index" class="h-full flex flex-col">
                                        <!-- Render Table (handles both table and grid views based on currentView) -->
                                        <Table
                                            v-if="item.columns"
                                            :table="item"
                                            :records="item.records || []"
                                            :pagination="item.pagination"
                                            :record-actions="item.recordActions || []"
                                            :bulk-actions="item.bulkActions || []"
                                            :filter-indicators="item.filterIndicators || []"
                                            :resource-slug="item.resourceSlug || ''"
                                            :query-route="item.queryRoute || ''"
                                            :current-view="currentView || 'table'"
                                        />

                                        <!-- Render Schema (InfoList - no actions) -->
                                        <Schema
                                            v-else-if="item.fields || item.schema"
                                            :schema="item.schema || item.fields || []"
                                            :form-controller="formController"
                                        />

                                        <!-- Fallback for unknown types -->
                                        <div v-else>
                                            <pre>{{ item }}</pre>
                                        </div>
                                    </div>

                                    <!-- Relation Managers (for View/Edit pages with InfoList) -->
                                    <RelationManagers
                                        v-if="hasRelationManagers && hasInfoListSchema"
                                        :relation-managers="relationManagers"
                                        :owner-record-id="record?.id"
                                        :resource-slug="props.resourceSlug"
                                        :panel-id="panelId || ''"
                                    />
                                </div>
                            </template>
                        </ErrorProvider>
                    </div>

                    <!-- Slot for programmatic content -->
                    <slot v-else>
                        <!-- Default empty state for custom pages -->
                        <div
                            class="flex h-full items-center justify-center rounded-lg border border-dashed p-8"
                        >
                            <div class="text-center">
                                <h3 class="text-lg font-semibold">
                                    Custom Page Content
                                </h3>
                                <p class="mt-2 text-sm text-muted-foreground">
                                    This is a standalone page. You can add
                                    custom components and content here.
                                </p>
                            </div>
                        </div>
                    </slot>
                </div>
            </div>
        </template>

        <template v-else>
            <!-- Non-panel layouts (like AuthCard) render content directly -->
            <div v-if="content" ref="contentRef" v-html="content" />

            <!-- Render Schema Form if available -->
            <div v-else-if="schema && schema.length">
                <!-- Status Message -->
                <div
                    v-if="status"
                    class="mb-4 rounded-md bg-green-50 p-4 text-sm font-medium text-green-600 dark:bg-green-950 dark:text-green-400"
                >
                    {{ status }}
                </div>

                <!-- Top Hook -->
                <component
                    v-if="topHook && typeof topHook === 'object'"
                    :is="topHook.component"
                    v-bind="topHook.props || {}"
                    class="mb-6"
                />
                <div
                    v-else-if="topHook && typeof topHook === 'string'"
                    v-html="topHook"
                    class="mb-6"
                />

                <!-- Use regular form when actions are present (ActionButton handles submission) -->
                <form
                    v-if="actionsWithUrl && actionsWithUrl.length"
                    @submit.prevent="handleFormSubmit"
                    class="flex flex-col gap-6"
                >
                    <ErrorProvider :errors="$page.props.errors || {}">
                        <!-- Render Schema -->
                        <Schema ref="formRendererRef" :schema="schema" :form-controller="formController" />

                        <!-- Submit Actions -->
                        <div class="flex flex-col gap-4">
                            <ActionButton
                                v-for="action in actionsWithUrl"
                                :key="action.name"
                                v-bind="action"
                                :getFormData="() => formRendererRef?.getFormData()"
                            />
                        </div>

                        <!-- Social Login -->
                        <SocialLogin
                            v-if="socialProviders && socialProviders.length > 0"
                            :providers="socialProviders"
                            :redirectUrl="socialRedirectUrl"
                        >
                            {{ trans('laravilt-auth::auth.social.or_continue_with') }}
                        </SocialLogin>

                        <!-- Auth Footer Links -->
                        <div v-if="canRegister && registerUrl" class="text-center text-sm text-muted-foreground">
                            {{ trans('laravilt-auth::auth.login.no_account') }}
                            <Link
                                :href="registerUrl"
                                class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                            >
                                {{ trans('laravilt-auth::auth.login.sign_up') }}
                            </Link>
                        </div>

                        <div v-if="canLogin && loginUrl" class="text-center text-sm text-muted-foreground">
                            {{ trans('laravilt-auth::auth.register.have_account') }}
                            <Link
                                :href="loginUrl"
                                class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                            >
                                {{ trans('laravilt-auth::auth.register.sign_in') }}
                            </Link>
                        </div>

                        <!-- Two-Factor Recovery Code Link -->
                        <div v-if="hasTwoFactorRecovery && recoveryUrl" class="text-center text-sm text-muted-foreground">
                            {{ trans('laravilt-auth::auth.two_factor_challenge.lost_device') }}
                            <Link
                                :href="recoveryUrl"
                                class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                            >
                                {{ trans('laravilt-auth::auth.two_factor_challenge.use_recovery') }}
                            </Link>
                        </div>

                        <!-- Passkey Authentication Option -->
                        <div v-if="hasPasskeys && passkeyLoginOptionsUrl && passkeyLoginUrl" class="text-center text-sm text-muted-foreground">
                            {{ trans('laravilt-auth::auth.common.or') }}
                            <button
                                type="button"
                                @click="handlePasskeyLogin"
                                class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                            >
                                {{ trans('laravilt-auth::auth.login.use_passkey') }}
                            </button>
                        </div>

                        <!-- Magic Link Authentication Option -->
                        <div v-if="hasMagicLinks && magicLinkSendUrl" class="text-center text-sm text-muted-foreground">
                            {{ trans('laravilt-auth::auth.common.or') }}
                            <button
                                type="button"
                                @click="handleSendMagicLink"
                                :disabled="sendingMagicLink"
                                class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                {{ sendingMagicLink ? trans('laravilt-auth::auth.common.processing') : trans('laravilt-auth::auth.magic_link.send') }}
                            </button>
                        </div>
                    </ErrorProvider>
                </form>

                <!-- Use Inertia Form when no actions (traditional form submission) -->
                <Form
                    v-else
                    :action="computedFormAction"
                    :method="formMethod"
                    #default="{ errors, processing }"
                    class="flex flex-col gap-6"
                >
                    <ErrorProvider :errors="errors">
                        <!-- Render Schema -->
                        <Schema :schema="schema" :form-controller="formController" />
                    </ErrorProvider>
                </Form>

                <!-- Bottom Hook -->
                <component
                    v-if="bottomHook && typeof bottomHook === 'object' && hookComponents[bottomHook.component]"
                    :is="hookComponents[bottomHook.component]"
                    v-bind="bottomHook.props || {}"
                    class="mt-6"
                />
                <div
                    v-else-if="bottomHook && typeof bottomHook === 'string'"
                    v-html="bottomHook"
                    class="mt-6"
                />
            </div>

            <slot v-else />
        </template>
    </component>
</template>
