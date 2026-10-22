import React from 'react';
import {Head, router, useForm} from '@inertiajs/react';
import {ShieldCheck} from 'lucide-react';
import StoreLayout from '../../Layouts/StoreLayout';
import TwoFactorCodeInput from '../../Components/TwoFactorCodeInput';

export default function TwoFactorChallenge() {
    const form = useForm({code: '', recovery_code: ''});

    return <StoreLayout>
        <Head title="Verify Sign In"/>
        <div className="max-w-md mx-auto px-5 py-16">
            <form className="panel space-y-5" onSubmit={event => {
                event.preventDefault();
                form.post('/two-factor-challenge', {onFinish: () => form.reset()});
            }}>
                <ShieldCheck className="text-ocean" size={36}/>
                <h1 className="text-3xl font-black">Verify your sign-in</h1>
                <p className="text-sm text-slate-600">Enter the 6-digit code from your authenticator app. If you lost access to your app, use one of your saved recovery codes.</p>
                <TwoFactorCodeInput form={form}/>
                <button className="btn w-full" disabled={form.processing}>{form.processing ? 'Verifying…' : 'Verify and sign in'}</button>
                <button type="button" className="text-sm font-bold text-ocean" disabled={form.processing} onClick={() => router.delete('/two-factor-challenge')}>Back to sign in</button>
            </form>
        </div>
    </StoreLayout>;
}
