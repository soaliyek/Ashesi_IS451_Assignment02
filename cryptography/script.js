// ---------------------- | script.js
// AES-GCM Encryption test
// Don't really know how to do it outside of a browser, but let's see what we can do
//
//
//-----------------------

// Toolbox
const crypto = require('crypto');

// Engine test
console.log("Hello World!");


// Learning Encoding and Decoding
const message = "Hey there! How are you?";

const encoder = new TextEncoder();
const decoder = new TextDecoder();

const encodedMessage = encoder.encode(message);
const decodedMessage = decoder.decode(encodedMessage);


console.log("---- Message:", message);
console.log("---- Encoded Message:", encodedMessage);
console.log("---- Decoded Message:", decodedMessage);

/** LESSON
 * Encode just gives the decimal value of the characters (bytes)
*/



// Learn and Test Crypto
const salt = crypto.getRandomValues(new Uint8Array(16));
console.log(salt);


function encodeMessage(message) {
    const encoder = new TextEncoder();
    return encoder.encode(message);
}

function decodeMEssage(encodedMessage) {
    const decoder = new TextDecoder();
    return decoder.decode(encodeMessage);
}

function encryptMessage(key, message) {
    const initializationVector = crypto.getRandomValues(new Uint8Array(12));
    return crypto.subtle.encrypt({ name: "AES-GCM", initializationVector }, key, message);
}

function decryptMessage(key, encryptedMessage) {
    
}