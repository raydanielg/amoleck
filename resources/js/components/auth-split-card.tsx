import type { ReactNode } from 'react';
import { Card, CardContent } from '@/components/ui/card';
import { cn } from '@/lib/utils';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

export function AuthSplitCard({
    children,
    footer,
    className,
    ...props
}: React.ComponentProps<'div'> & {
    children: ReactNode;
    footer?: ReactNode;
}) {
    return (
        <div className={cn('flex flex-col gap-6', className)} {...props}>
            <Card className="overflow-hidden p-0">
                <CardContent className="grid p-0 md:grid-cols-2">
                    <div className="p-6">{children}</div>
                    <div className="relative hidden bg-muted md:block">
                        <img
                            src="/image-side.jpg"
                            alt={appName}
                            className="absolute inset-0 h-full w-full object-cover"
                        />
                    </div>
                </CardContent>
            </Card>
            {footer}
        </div>
    );
}
