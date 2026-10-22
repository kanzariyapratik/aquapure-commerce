import React from 'react';
import {Head, Link, useForm} from '@inertiajs/react';
import {ShieldCheck} from 'lucide-react';
import AdminLayout from '../../Layouts/AdminLayout';
import StoreLayout from '../../Layouts/StoreLayout';
import TwoFactorCodeInput from '../../Components/TwoFactorCodeInput';

function CurrentPassword({form, id}) {
    return <label className="block" htmlFor={id}>Current password
        <input id={id} name="current_password" type="password" autoComplete="current-password" required className="field mt-1" value={form.data.current_password} onChange={event => form.setData('current_password', event.target.value)} aria-invalid={!!form.errors.current_password}/>
        {form.errors.current_password && <span role="alert" className="block text-sm text-red-600 mt-1">{form.errors.current_password}</span>}
    </label>;
}

export default function Security({admin, enabled, required, setup, recoveryCodes, recoveryCodesRemaining}) {
    const begin = useForm({current_password: ''});
    const confirm = useForm({code: ''});
    const manage = useForm({current_password: '', code: '', recovery_code: ''});
    const Layout = admin ? AdminLayout : StoreLayout;

    return <Layout title="Account security">
        <Head title="Account Security"/>
        <div className={admin ? 'max-w-2xl space-y-6' : 'max-w-2xl mx-auto px-5 py-14 space-y-6'}>
            {!admin && <h1 className="text-3xl font-black">Account security</h1>}
            <section className="panel space-y-5">
                <div className="flex items-start gap-3"><ShieldCheck className="text-ocean shrink-0" size={28}/><div><h2 className="text-xl font-bold">Two-factor authentication</h2><p className="text-sm text-slate-600 mt-1">{enabled ? 'Enabled. Your password and a second factor are required at sign-in.' : 'Add an extra layer of protection with an authenticator app.'}</p></div></div>
                {required && !enabled && <p role="status" className="rounded-xl bg-amber-50 border border-amber-200 p-4 text-sm text-amber-900">Admin accounts must enable two-factor authentication before using the admin panel.</p>}
                {!enabled && !setup && confirm.errors.code && <p role="alert" className="text-sm text-red-600">{confirm.errors.code}</p>}
                {!enabled && !setup && <form className="space-y-5" onSubmit={event => {
                    event.preventDefault();
                    begin.post('/account/two-factor', {onFinish: () => begin.reset()});
                }}>
                    <p className="text-sm text-slate-600">Use Google Authenticator, Microsoft Authenticator, or another app that supports time-based codes.</p>
                    <CurrentPassword form={begin} id="setup-password"/>
                    <button className="btn" disabled={begin.processing}>{begin.processing ? 'Preparing…' : 'Set up authenticator'}</button>
                </form>}
                {!enabled && setup && <div className="space-y-5">
                    <ol className="list-decimal pl-5 space-y-2 text-sm text-slate-600"><li>Open your authenticator app and add a new account.</li><li>Scan this QR code, or enter the setup key manually.</li><li>Enter the current 6-digit code below to finish setup. Setup expires after 10 minutes.</li></ol>
                    <img src={setup.qrCode} alt="Scan this QR code with your authenticator app" width="240" height="240" className="max-w-full mx-auto"/>
                    <div className="rounded-xl bg-slate-50 p-4"><p className="text-xs text-slate-500 mb-2">Setup key — keep this private</p><code className="font-mono text-sm break-all select-all">{setup.secret}</code></div>
                    <form className="space-y-4" onSubmit={event => {
                        event.preventDefault();
                        confirm.post('/account/two-factor/confirm', {onFinish: () => confirm.reset()});
                    }}>
                        <label className="block" htmlFor="confirm-code">Authenticator code<input id="confirm-code" name="code" type="text" inputMode="numeric" autoComplete="one-time-code" pattern="[0-9]{6}" maxLength={6} required className="field mt-1 font-mono" value={confirm.data.code} onChange={event => confirm.setData('code', event.target.value)}/></label>
                        {confirm.errors.code && <p role="alert" className="text-sm text-red-600">{confirm.errors.code}</p>}
                        <button className="btn" disabled={confirm.processing}>{confirm.processing ? 'Confirming…' : 'Confirm and enable 2FA'}</button>
                    </form>
                </div>}
                {enabled && <p className="text-sm text-slate-600">{recoveryCodesRemaining} recovery codes remaining. Each recovery code works once.</p>}
            </section>
            {recoveryCodes.length > 0 && <section className="panel space-y-4" aria-label="Your recovery codes">
                <h2 className="text-xl font-bold">Save your recovery codes</h2>
                <p className="text-sm text-slate-600">These codes are shown only now. Store them in a password manager or another safe place. Each code can be used once if you cannot access your authenticator.</p>
                <ul className="rounded-xl bg-slate-50 p-4 grid gap-3 font-mono text-xs sm:text-sm">{recoveryCodes.map(code => <li key={code} className="select-all break-all">{code}</li>)}</ul>
                <a className="btn-light" download="aquapure-recovery-codes.txt" href={`data:text/plain;charset=utf-8,${encodeURIComponent('AquaPure recovery codes — keep private\n\n' + recoveryCodes.join('\n'))}`}>Download recovery codes</a>
            </section>}
            {enabled && <section className="panel space-y-5">
                <h2 className="text-xl font-bold">Manage recovery codes{!required && ' and 2FA'}</h2>
                <p className="text-sm text-slate-600">Confirm your password and a fresh authenticator code or an unused recovery code. Generating new recovery codes invalidates all previous codes. If a code was just used, wait for the next one.</p>
                <form className="space-y-5" onSubmit={event => {
                    event.preventDefault();
                    manage.post('/account/two-factor/recovery-codes', {preserveScroll: true, onFinish: () => manage.reset()});
                }}>
                    <CurrentPassword form={manage} id="manage-password"/>
                    <TwoFactorCodeInput form={manage} idPrefix="manage"/>
                    <div className="flex flex-wrap gap-3">
                        <button className="btn" disabled={manage.processing}>Generate new recovery codes</button>
                        {!required && <button type="submit" className="btn-light text-red-700" disabled={manage.processing} onClick={event => {
                            if (!event.currentTarget.form.reportValidity()) return;
                            event.preventDefault();
                            manage.delete('/account/two-factor', {preserveScroll: true, onFinish: () => manage.reset()});
                        }}>Disable 2FA</button>}
                    </div>
                </form>
                {required && <p className="text-sm text-slate-600">Two-factor authentication cannot be disabled for admin accounts.</p>}
            </section>}
            <Link href={admin ? '/admin/password' : '/account/password'} className="inline-block text-sm font-bold text-ocean">Change password</Link>
        </div>
    </Layout>;
}
