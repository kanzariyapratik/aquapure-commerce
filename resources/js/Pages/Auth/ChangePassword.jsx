import React from 'react';
import {Head, useForm} from '@inertiajs/react';
import StoreLayout from '../../Layouts/StoreLayout';
import AdminLayout from '../../Layouts/AdminLayout';

export default function ChangePassword({admin = false}) {
    const {data, setData, put, errors, processing, reset} = useForm({
        current_password: '', password: '', password_confirmation: '',
    });
    const Layout = admin ? AdminLayout : StoreLayout;

    return <Layout title="Change password">
        <Head title="Change Password"/>
        <div className={admin ? 'max-w-lg' : 'max-w-lg mx-auto px-5 py-14'}>
            {!admin && <h1 className="text-3xl font-black mb-6">Change password</h1>}
            <form className="panel space-y-5" onSubmit={event => {
                event.preventDefault();
                put(admin ? '/admin/password' : '/account/password', {
                    preserveScroll: true,
                    onSuccess: () => reset(),
                    onError: () => reset('current_password', 'password', 'password_confirmation'),
                });
            }}>
                <p className="text-sm text-slate-600">Enter your current password and choose a new password with 12–64 characters, including letters and numbers.</p>
                {[
                    ['current_password', 'Current password', 'current-password'],
                    ['password', 'New password', 'new-password'],
                    ['password_confirmation', 'Confirm new password', 'new-password'],
                ].map(([name, label, autoComplete]) => <label className="block" key={name}>
                    {label}
                    <input name={name} type="password" autoComplete={autoComplete} minLength={name === 'current_password' ? undefined : 12} className="field mt-1" value={data[name]} onChange={event => setData(name, event.target.value)} required aria-invalid={!!errors[name]} aria-describedby={errors[name] ? `${name}-error` : undefined}/>
                    {errors[name] && <p id={`${name}-error`} role="alert" className="text-red-600 text-sm mt-1">{errors[name]}</p>}
                </label>)}
                <button className="btn w-full" disabled={processing}>{processing ? 'Saving…' : 'Change password'}</button>
            </form>
        </div>
    </Layout>;
}
