const signupFormElement = document.getElementById('signup');
const signupCodeInputElement = document.getElementById('signupCode');
const registerButton = document.getElementById('registerButton');
const remarksElement = document.getElementById('remarks');


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

// Request to begin registration + Request to finish registration
async function requestKeyRegistration(body) {

    // Send request to the api
    const response = await fetch('../api/register.php', 
        {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        }
    );

    // Response in Json
    const result = await response.json();

    if (!response.ok || !result.ok) {
        throw new Error(result.message || 'Registration could not be completed.');
    }

    return result;
}

// Request to login
async function requestLogin(body) {

    // Send request to the api
    const response = await fetch('../api/login.php', 
        {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        }
    );

    // Response in Json
    const result = await response.json();

    if (!response.ok || !result.ok) {
        throw new Error(result.message || 'We could not log you in.');
    }

    return result;
}

// Credential Generator
async function createCredential(credentialArgs) {
    const credential = await navigator.credentials.create(credentialArgs);
    if (!credential) {
        remarksElement.textContent = 'Credential generation failed.';
        throw new Error('Credential generation failed.');
    }

    return credential;
}

async function getAssertion(credentialArgs) {
    const assertion = await navigator.credentials.get(credentialArgs);

    if (!assertion) {
        remarksElement.textContent = 'Passkey verification failed.';
        throw new Error('Passkey verification failed.');
    }

    return assertion;
}

function getCredentialRegistrationArgs(options){
    const credentialRegistrationArgs = {
        publicKey: {
            // Scope of the key
            rp: {
                name: 'University Local Portal',
                id: 'localhost'
            },
    
            // Associated user
            user: {
                id: options.user.id,
                name: options.user.email,
                displayName: options.user.firstName + options.user.lastName
            },
    
            // Encryption Standard (Algorithm): ES256
            pubKeyCredParams: [{
                type: 'public-key',
                alg: -7
            }],
    
            // Don't need attestation
            attestation: 'none',
            timeout: 60000,
    
            // Now, the challenge
            challenge: options.challenge
        }
    };

    return credentialRegistrationArgs;
}

function getCredentialVerificationArgs(options){
    const credentialVerificationArgs = {
        publicKey: {
            rpId: 'localhost',
    
            // Now, the challenge
            challenge: options.challenge,

            allowCredentials: [{
                type: 'public-key',
                id: options.credentialID
            }],

            userVerification: 'required',
            timeout: 60000
        }
    };

    return credentialVerificationArgs;
}

// Listen to the form submission
signupFormElement.addEventListener('submit', async (event) => {
    event.preventDefault();
    remarksElement.textContent = '';

    // Veryfy if browser supports credentials
    if(!window.PublicKeyCredential || !navigator.credential){
        remarksElement.textContent = 'Your browser does not support passkey registrations.';
        return;
    }

    // Disable the button to avoid another submission
    registerButton.disabled = true;

    // Start registration
    try {
        let requestBody = {
            action: 'start',
            signupCode: signupCodeInputElement.value.trim()
        }

        // Get options from server
        const { options } = await requestKeyRegistration(requestBody);

        // IMPORTANT STEP: Transform the options properties
        options.challenge = decodeBase64Url(options.challenge);
        options.user.id = decodeBase64Url(options.user.id);

        // Generate credential
        const registrationArgs = getCredentialRegistrationArgs(options);
        const credential = await createCredential(registrationArgs);

        requestBody = {
            action: 'finish',
            credential: {
                id: encodeBase64Url(credential.rawId),
                type: credential.type,
                clientDataJSON: encodeBase64Url(credential.response.clientDataJSON),
                attestationObject: encodeBase64Url(credential.response.attestationObject)
            }
        }

        // Send credential to server
        const { confirmation } = await requestKeyRegistration(requestBody);

        // IMPORTANT STEP: Transform the options properties

        // Authorization
        if (confirmation.verdict === 'success'){
            remarksElement.textContent = 'Passkey Registered Successfully. Redirecting to Dashboard...';
            window.location.replace('../public/dashboard.php');
            //window.location.href = '../public/dashboard.php';
        }

    }
    catch (error) {
        remarksElement.textContent = error instanceof Error
            ? error.message
            : 'Registration could not be completed. Please try again.';
        registerButton.disabled = false;
    }
});


/////// SIGN IN
const loginButton = document.getElementById('loginButton');
const signinFormElement = document.getElementById('signin');
const emailElement = document.getElementById('email');

signinFormElement.addEventListener('submit', async (event) => {
    event.preventDefault();
    remarksElement.textContent = '';

    // Veryfy if browser supports credentials
    if(!window.PublicKeyCredential || !navigator.credential){
        remarksElement.textContent = 'Your browser does not support passkey login.';
        return;
    }

    // Disable the button to avoid another submission
    loginButton.disabled = true;

    // Start Login
    try {
        let requestBody = {
            action: 'start',
            email: emailElement.value.trim()
        }

        // Get options from server
        const { options } = await requestLogin(requestBody);

        // IMPORTANT STEP: Transform the options properties
        options.challenge = decodeBase64Url(options.challenge);
        options.credentialID = decodeBase64Url(options.credentialID);

        // Generate credential
        const verificationArgs = getCredentialVerificationArgs(options);
        const assertion = await getAssertion(verificationArgs);

        requestBody = {
            action: 'finish',
            assertion: {
                id: encodeBase64Url(assertion.rawId),
                type: assertion.type,
                clientDataJSON: encodeBase64Url(assertion.response.clientDataJSON),
                authenticatorData: encodeBase64Url(assertion.response.authenticatorData),
                signature: encodeBase64Url(assertion.response.signature)
            }
        }

        // Send credential to server
        const { confirmation } = await requestLogin(requestBody);

        // IMPORTANT STEP: Transform the options properties

        // Authorization
        if (confirmation.verdict === 'success'){
            remarksElement.textContent = 'Logged In Successfully. Redirecting to Dashboard...';
            window.location.replace('../public/dashboard.php');
            //window.location.href = '../public/dashboard.php';
        }

    }
    catch (error) {
        remarksElement.textContent = error instanceof Error
            ? error.message
            : 'Login could not be completed. Please try again.';
        loginButton.disabled = false;
    }
});


/**
 *  IMPROVEMENTS TO MAKE
 * 1. LOGIN CREDENTIAL
 * We are assuming a user only registers one credential. 
 * It could be many, and so, the server will return an array of those
 * and we should be able to consider that and check them all
 * But for the moment, we are going to let the server return only one credential
 * In localhost, a user only uses one browser and registers only one credential
 * 
 * 2. MODULARITY PROBLEM
 * This is something to solve
 * We can use a class for our two server request functions
 * But for now, we just want to get it working. We will look into
 * improvements later if time permits
 * 
 */