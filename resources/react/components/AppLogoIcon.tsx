import type { SVGAttributes } from 'react';

interface AppLogoIconProps extends SVGAttributes<SVGElement> {
    className?: string;
}

export default function AppLogoIcon({ className, ...attrs }: AppLogoIconProps) {
    return (
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" className={className} {...attrs}>
            <title>Laravilt</title>
            <rect x="4" y="22.4" width="18.4" height="18.4" fill="#FF2D20"/>
            <rect x="22.4" y="40.8" width="18.4" height="18.4" fill="#FF2D20"/>
            <rect x="4" y="59.2" width="18.4" height="18.4" fill="#FF2D20"/>
            <rect x="40.8" y="22.4" width="18.4" height="18.4" fill="#9553E9"/>
            <rect x="40.8" y="59.2" width="18.4" height="18.4" fill="#9553E9"/>
            <rect x="59.2" y="40.8" width="18.4" height="18.4" fill="#9553E9"/>
            <rect x="77.6" y="22.4" width="18.4" height="18.4" fill="#9553E9"/>
        </svg>
    );
}
