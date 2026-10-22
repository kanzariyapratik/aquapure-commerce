import React from 'react';
import {Head, Link, useForm} from '@inertiajs/react';
import StoreLayout from '../../Layouts/StoreLayout';

export default function ForgotPassword() {
    const {data, setData, post, errors, processing} = useForm({email: ''});

    return <StoreLayout>
        <Head title="Forgot Password"/>
        <div className="max-w-md mx-auto px-5 py-20">
            <form className="panel space-y-5" onSubmit={event => {
                event.preventDefault();
                post('/forgot-password');
            }}>
                <h1 className="text-3xl font-black">Forgot password?</h1>
                <p className="text-sm text-slate-600">Enter your account email and we'll send you a link to reset your password. This works for customer and admin accounts.</p>
                <label className="block" htmlFor="email">Email address</label>
                <input id="email" name="email" type="email" autoComplete="email" className="field" value={data.email} onChange={event => setData('email', event.target.value)} required autoFocus aria-invalid={!!errors.email} aria-describedby={errors.email ? 'email-error' : undefined}/>
                {errors.email && <p id="email-error" role="alert" className="text-red-600 text-sm">{errors.email}</p>}
                <button className="btn w-full" disabled={processing}>{processing ? 'Sending…' : 'Send reset link'}</button>
                <Link href="/login" className="block text-sm text-ocean font-bold">Back to sign in</Link>
            </form>
        </div>
    </StoreLayout>;
}
