const signupForm = document.getElementById('signup');
const signupCodeInput = document.getElementById('signupCode');
const registerButton = document.getElementById('registerButton');
const remarks = document.getElementById('remarks');

function decodeBase64Url(value) {
    const base64 = value.replace(/-/g, '+').replace(/_/g, '/');
    const padded = base64 + '='.repeat((4 - base64.length % 4) % 4);
    const binary = window.atob(padded);
    return Uint8Array.from(binary, (character) => character.charCodeAt(0));
}

function encodeBase64Url(value) {
    const bytes = new Uint8Array(value);
    let binary = '';
    for (const byte of bytes) {
        binary += String.fromCharCode(byte);
    }
    return window.btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, '');
}

async function sendRegistrationRequest(body) {
    const response = await fetch('../api/registerkey.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
    });
    const result = await response.json();
    if (!response.ok || !result.ok) {
        throw new Error(result.message || 'Registration could not be completed.');
    }
    return result;
}

signupForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    remarks.textContent = '';

    if (!window.PublicKeyCredential || !navigator.credentials) {
        remarks.textContent = 'This browser does not support passkey registration.';
        return;
    }

    registerButton.disabled = true;
    try {
        const { options } = await sendRegistrationRequest({
            action: 'begin',
            signupCode: signupCodeInput.value.trim()
        });

        options.challenge = decodeBase64Url(options.challenge);
        options.user.id = decodeBase64Url(options.user.id);
        
        const credential = await navigator.credentials.create({ publicKey: options });
        if (!credential) {
            throw new Error('Passkey registration was cancelled.');
        }

        await sendRegistrationRequest({
            action: 'finish',
            credential: {
                id: credential.id,
                rawId: encodeBase64Url(credential.rawId),
                type: credential.type,
                response: {
                    clientDataJSON: encodeBase64Url(credential.response.clientDataJSON),
                    attestationObject: encodeBase64Url(credential.response.attestationObject)
                }
            }
        });

        remarks.textContent = 'Passkey registered. Redirecting to your portal...';
        window.location.replace('../public/dashboard.php');
    } catch (error) {
        remarks.textContent = error instanceof Error
            ? error.message
            : 'Registration could not be completed. Please try again.';
        registerButton.disabled = false;
    }
});