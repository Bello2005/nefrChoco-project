import AppLogoIcon from './app-logo-icon';

export default function AppLogo() {
    return (
        <>
            <div className="bg-primary text-primary-foreground flex aspect-square size-9 items-center justify-center rounded-xl shadow-sm">
                <AppLogoIcon className="size-5.5" />
            </div>
            <div className="ml-1.5 grid flex-1 text-left">
                <span className="font-display truncate text-sm leading-tight font-extrabold">IPS NefroChocó</span>
                <span className="text-muted-foreground truncate text-[11px] leading-tight">Telemedicina ECNT</span>
            </div>
        </>
    );
}
