import { usePage } from '@inertiajs/react';
import { useEffect, type ReactNode } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { NotificationContainer } from '@laravilt/notifications/app';

interface CardLayoutProps {
    title?: string;
    description?: string;
    children?: ReactNode;
}

export default function CardLayout({ title, description, children }: CardLayoutProps) {
    const page = usePage();
    const pageProps = page.props as any;

    // Get the panel or home URL
    const panelId = pageProps.panelId;
    const homeUrl = panelId ? `/${panelId}` : '/';

    // Get panel data
    const panel = pageProps.panel;
    const brandLogo = panel?.brandLogo;
    const brandName = panel?.brandName;
    const font = panel?.font;
    const fontUrl: string | undefined = font?.url;
    const fontFamily: string | undefined = font?.family;

    // Apply font styles when component mounts / font changes
    useEffect(() => {
        if (font && typeof document !== 'undefined') {
            // Load Google Font if URL is provided
            if (fontUrl) {
                const existingLink = document.querySelector(`link[href="${fontUrl}"]`);
                if (!existingLink) {
                    const link = document.createElement('link');
                    link.rel = 'stylesheet';
                    link.href = fontUrl;
                    document.head.appendChild(link);
                }
            }

            // Apply font family to body
            if (fontFamily) {
                document.body.style.fontFamily = `"${fontFamily}", sans-serif`;
            }
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [fontUrl, fontFamily]);

    return (
        <div
            className="isolate flex min-h-svh max-w-screen flex-col overflow-x-clip bg-background"
            style={fontFamily ? { fontFamily: `'${fontFamily}', sans-serif` } : {}}
        >
            <div className="mx-auto flex w-full max-w-md flex-1 flex-col justify-center border-x border-line">
                {/* Logo */}
                <div className="screen-line-top screen-line-bottom flex items-center justify-center py-5">
                    <a href={homeUrl} className="flex flex-col items-center gap-2 font-medium">
                        <div className="flex items-center justify-center">
                            {brandLogo ? (
                                // Use panel brand logo if available
                                <img
                                    src={brandLogo}
                                    alt={brandName || title}
                                    className="h-10 w-auto max-w-[200px] object-contain"
                                />
                            ) : (
                                // Fallback to AppLogoIcon
                                <AppLogoIcon className="size-10" />
                            )}
                        </div>
                        <span className="sr-only">{brandName || title}</span>
                    </a>
                </div>

                <div className="stripe-divider" />

                {/* Title and Description */}
                <div className="screen-line-top screen-line-bottom flex flex-col gap-1 px-6 pt-8 pb-6 text-center md:px-10">
                    <h1 className="text-xl font-medium">{title}</h1>
                    {description && <p className="text-center text-sm text-muted-foreground">{description}</p>}
                </div>

                {/* Content */}
                <div className="screen-line-bottom px-6 py-8 md:px-10">{children}</div>

                <div className="stripe-divider" />
            </div>
            <NotificationContainer />
        </div>
    );
}
