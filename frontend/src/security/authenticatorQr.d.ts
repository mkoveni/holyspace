/** Encode the existing provisioning URI locally; never send authenticator secrets to an image service. */
export declare function authenticatorQr(uri: string): Promise<string>;
