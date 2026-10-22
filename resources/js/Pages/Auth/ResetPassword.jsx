import React from 'react';
import {Head, Link, useForm} from '@inertiajs/react';
import StoreLayout from '../../Layouts/StoreLayout';

export default function ResetPassword({token, email}) {
    const {data, setData, post, errors, processing, reset} = useForm({
        token, email, password: '', password_confirmation: '',
    });

    return <StoreLayout>
        <Head title="Reset Password"/>
        <div className="max-w-md mx-auto px-5 py-20">
            <form className="panel space-y-5" onSubmit={event => {
                event.preventDefault();
                post('/reset-password', {onFinish: () => reset('password', 'password_confirmation')});
            }}>
                <h1 className="text-3xl font-black">Reset password</h1>
                <p className="text-sm text-slate-600">Choose a new password with 12–64 characters, including letters and numbers.</p>
                {[
                    ['email', 'Email address', 'email', 'email'],
                    ['password', 'New password', 'password', 'new-password'],
                    ['password_confirmation', 'Confirm new password', 'password', 'new-password'],
                ].map(([name, label, type, autoComplete]) => <label className="block" key={name}>
                    {label}
                    <input name={name} type={type} autoComplete={autoComplete} minLength={type === 'password' ? 12 : undefined} className="field mt-1" value={data[name]} onChange={event => setData(name, event.target.value)} required aria-invalid={!!errors[name]} aria-describedby={errors[name] ? `${name}-error` : undefined}/>
                    {errors[name] && <p id={`${name}-error`} role="alert" className="text-red-600 text-sm mt-1">{errors[name]}</p>}
                </label>)}
                {errors.token && <p role="alert" className="text-red-600 text-sm">{errors.token}</p>}
                <button className="btn w-full" disabled={processing}>{processing ? 'Resetting…' : 'Reset password'}</button>
                <Link href="/forgot-password" className="block text-sm text-ocean font-bold">Request a new reset link</Link>
                <Link href="/login" className="block text-sm text-slate-600">Back to sign in</Link>
            </form>
        </div>
    </StoreLayout>;
}
