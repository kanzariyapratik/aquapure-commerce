import React, {useState} from 'react';

export default function TwoFactorCodeInput({form, idPrefix = 'two-factor'}) {
    const [useRecovery, setUseRecovery] = useState(false);
    const name = useRecovery ? 'recovery_code' : 'code';
    const error = form.errors.code || form.errors.recovery_code;

    return <div className="space-y-3">
        <label className="block" htmlFor={`${idPrefix}-code`}>{useRecovery ? 'Recovery code' : 'Authenticator code'}</label>
        <input id={`${idPrefix}-code`} name={name} type="text" className="field font-mono" value={form.data[name]} onChange={event => form.setData(name, event.target.value)} autoComplete="one-time-code" inputMode={useRecovery ? 'text' : 'numeric'} pattern={useRecovery ? undefined : '[0-9]{6}'} maxLength={useRecovery ? 100 : 6} required spellCheck={false} aria-invalid={!!error} aria-describedby={error ? `${idPrefix}-error` : undefined}/>
        {error && <p id={`${idPrefix}-error`} role="alert" className="text-sm text-red-600">{error}</p>}
        <button type="button" className="text-sm font-bold text-ocean" onClick={() => {
            setUseRecovery(!useRecovery);
            form.setData(previous => ({...previous, code: '', recovery_code: ''}));
            form.clearErrors('code', 'recovery_code');
        }}>{useRecovery ? 'Use an authenticator code instead' : 'Use a recovery code instead'}</button>
    </div>;
}
