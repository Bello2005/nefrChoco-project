import { SVGProps } from 'react';

/** Marca NefroChocó: gota (agua/riñón) atravesada por una línea de pulso. */
export default function AppLogoIcon(props: SVGProps<SVGSVGElement>) {
    return (
        <svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" {...props}>
            <path
                d="M16 3.2c4.9 4.6 8.4 8.7 8.4 13.1A8.4 8.4 0 0 1 16 24.7a8.4 8.4 0 0 1-8.4-8.4c0-4.4 3.5-8.5 8.4-13.1Z"
                fill="currentColor"
                opacity="0.18"
            />
            <path
                d="M16 3.2c4.9 4.6 8.4 8.7 8.4 13.1A8.4 8.4 0 0 1 16 24.7a8.4 8.4 0 0 1-8.4-8.4c0-4.4 3.5-8.5 8.4-13.1Z"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinejoin="round"
            />
            <path d="M10.6 16.8h2.6l1.5-3.4 2.1 6 1.6-2.6h2.9" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
    );
}
