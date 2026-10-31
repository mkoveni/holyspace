package org.onechurch.communications.infrastructure;
import org.onechurch.communications.application.contract.PushTokens;
import org.onechurch.communications.domain.CommunicationException;
import org.springframework.beans.factory.annotation.Value;
import org.springframework.stereotype.Component;

import java.nio.charset.StandardCharsets;
import java.security.GeneralSecurityException;
import java.security.SecureRandom;
import java.util.Base64;

import javax.crypto.Cipher;
import javax.crypto.spec.GCMParameterSpec;
import javax.crypto.spec.SecretKeySpec;
@Component
public final class AesPushTokens implements PushTokens {
    private final byte[] key;
    public AesPushTokens(@Value("${PUSH_TOKEN_KEY:}") String configured) {
        byte[] parsed;
        try {
            parsed = Base64.getDecoder().decode(configured);
        } catch (IllegalArgumentException e) {
            parsed = new byte[0];
        }
        this.key = parsed;
    }
    public boolean available() {
        return key.length == 32;
    }
    private Cipher cipher(int mode, byte[] nonce) throws GeneralSecurityException {
        if (!available()) {
            throw CommunicationException.conflict("Configure the private push token encryption key.");
        }
        Cipher c = Cipher.getInstance("AES/GCM/NoPadding");
        c.init(mode, new SecretKeySpec(key, "AES"), new GCMParameterSpec(128, nonce));
        c.updateAAD("holyspace-push-v1".getBytes(StandardCharsets.UTF_8));
        return c;
    }
    public String encrypt(String token) {
        try {
            byte[] nonce = new byte[12];
            new SecureRandom().nextBytes(nonce);
            byte[] encoded = cipher(Cipher.ENCRYPT_MODE, nonce).doFinal(token.getBytes(StandardCharsets.UTF_8));
            return Base64.getEncoder().encodeToString(nonce) + ":" + Base64.getEncoder().encodeToString(encoded);
        } catch (GeneralSecurityException | IllegalArgumentException e) {
            throw CommunicationException.conflict("Push token encryption is unavailable.");
        }
    }
    public String decrypt(String encrypted) {
        try {
            String[] parts = encrypted.split(":", -1);
            if (parts.length != 2) {
                throw new IllegalArgumentException();
            }
            return new String(cipher(Cipher.DECRYPT_MODE, Base64.getDecoder().decode(parts[0]))
                .doFinal(Base64.getDecoder().decode(parts[1])), StandardCharsets.UTF_8);
        } catch (GeneralSecurityException | IllegalArgumentException e) {
            throw CommunicationException.conflict("Push token decryption is unavailable.");
        }
    }
}
