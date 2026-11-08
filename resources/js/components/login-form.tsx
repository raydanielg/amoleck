import { Form } from '@inertiajs/react';
import { LockPasswordIcon, Mail01Icon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { AuthSplitCard } from '@/components/auth-split-card';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Field,
    FieldDescription,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

export function LoginForm({
    status,
    canResetPassword,
    className,
    ...props
}: React.ComponentProps<'div'> & {
    status?: string;
    canResetPassword: boolean;
}) {
    return (
        <AuthSplitCard
            className={className}
            footer={
                <>
                    {status && (
                        <div className="text-center text-sm font-medium text-green-600">
                            {status}
                        </div>
                    )}
                    <FieldDescription className="px-6 text-center">
                        By clicking continue, you agree to our{' '}
                        <a href="#">Terms of Service</a> and{' '}
                        <a href="#">Privacy Policy</a>.
                    </FieldDescription>
                </>
            }
            {...props}
        >
            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <FieldGroup className="gap-5">
                        <div className="flex flex-col items-center gap-2 text-center">
                            <h1 className="text-2xl font-bold">Welcome back</h1>
                            <p className="text-balance text-muted-foreground">
                                Login to your {appName} account
                            </p>
                        </div>
                        <Field>
                            <FieldLabel htmlFor="email">Email</FieldLabel>
                            <div className="relative">
                                <HugeiconsIcon
                                    icon={Mail01Icon}
                                    strokeWidth={2}
                                    className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                />
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="email"
                                    placeholder="Enter your email"
                                    className="pl-9"
                                />
                            </div>
                            <InputError message={errors.email} />
                        </Field>
                        <Field>
                            <div className="flex items-center">
                                <FieldLabel htmlFor="password">
                                    Password
                                </FieldLabel>
                                {canResetPassword && (
                                    <TextLink
                                        href={request()}
                                        className="ml-auto text-sm"
                                        tabIndex={5}
                                    >
                                        Forgot your password?
                                    </TextLink>
                                )}
                            </div>
                            <div className="relative">
                                <HugeiconsIcon
                                    icon={LockPasswordIcon}
                                    strokeWidth={2}
                                    className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                />
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    required
                                    tabIndex={2}
                                    autoComplete="current-password"
                                    placeholder="Enter your password"
                                    className="pl-9"
                                />
                            </div>
                            <InputError message={errors.password} />
                        </Field>
                        <Field>
                            <div className="flex items-center space-x-3">
                                <Checkbox
                                    id="remember"
                                    name="remember"
                                    tabIndex={3}
                                />
                                <FieldLabel
                                    htmlFor="remember"
                                    className="font-normal"
                                >
                                    Remember me
                                </FieldLabel>
                            </div>
                        </Field>
                        <Field>
                            <Button
                                type="submit"
                                className="w-full"
                                tabIndex={4}
                                disabled={processing}
                                data-test="login-button"
                            >
                                {processing && <Spinner />}
                                Login
                            </Button>
                        </Field>
                    </FieldGroup>
                )}
            </Form>
        </AuthSplitCard>
    );
}
