import type { ImgHTMLAttributes } from 'react';

export default function AppLogoIcon(
    props: ImgHTMLAttributes<HTMLImageElement>,
) {
    return (
        <img
            src="/logo-icon.png"
            alt="Amoleck Physiotherapy Hospital"
            {...props}
        />
    );
}
