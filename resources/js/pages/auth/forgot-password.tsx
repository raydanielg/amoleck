import { Form, Head } from '@inertiajs/react';
import { Mail01Icon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { AuthSplitCard } from '@/components/auth-split-card';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import {
    Field,
    FieldDescription,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { email } from '@/routes/password';

export default function ForgotPassword({ status }: { status?: string }) {
    return (
        <>
            <Head title="Forgot password" />

            <div className="flex min-h-svh flex-col items-center justify-center bg-muted p-6 md:p-10">
                <div className="w-full max-w-sm md:max-w-4xl">
                    <AuthSplitCard
                        footer={
                            status && (
                                <div className="text-center text-sm font-medium text-green-600">
                                    {status}
                                </div>
                            )
                        }
                    >
                        <Form {...email.form()} className="flex flex-col gap-6">
                            {({ processing, errors }) => (
                                <FieldGroup className="gap-5">
                                    <div className="flex flex-col items-center gap-2 text-center">
                                        <h1 className="text-2xl font-bold">
                                            Forgot password
                                        </h1>
                                        <p className="text-balance text-muted-foreground">
                                            Enter your email to receive a
                                            password reset link
                                        </p>
                                    </div>
                                    <Field>
                                        <FieldLabel htmlFor="email">
                                            Email
                                        </FieldLabel>
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
                                                autoComplete="off"
                                                autoFocus
                                                placeholder="Enter your email"
                                                className="pl-9"
                                            />
                                        </div>
                                        <InputError message={errors.email} />
                                    </Field>
                                    <Field>
                                        <Button
                                            type="submit"
                                            className="w-full"
                                            disabled={processing}
                                            data-test="email-password-reset-link-button"
                                        >
                                            {processing && <Spinner />}
                                            Email password reset link
                                        </Button>
                                    </Field>
                                    <FieldDescription className="text-center">
                                        Or, return to{' '}
                                        <TextLink href={login()}>
                                            log in
                                        </TextLink>
                                    </FieldDescription>
                                </FieldGroup>
                            )}
                        </Form>
                    </AuthSplitCard>
                </div>
            </div>
        </>
    );
}
