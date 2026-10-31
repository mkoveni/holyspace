package org.onechurch.payments.infrastructure.gateway;
import org.onechurch.payments.application.contract.SubscriptionVault;
import org.onechurch.payments.domain.PaymentException;
import org.springframework.stereotype.Component;

import java.nio.charset.StandardCharsets;
import java.security.GeneralSecurityException;
import java.security.SecureRandom;
import java.util.Base64;

import javax.crypto.Cipher;
import javax.crypto.spec.GCMParameterSpec;
import javax.crypto.spec.SecretKeySpec;
@Component
public final class AesSubscriptionVault implements SubscriptionVault {
    private final PaymentSettings settings;
    public AesSubscriptionVault(PaymentSettings settings) {
        this.settings = settings;
    }
    private Cipher cipher(int mode, byte[] nonce) throws GeneralSecurityException {
        byte[] key = Base64.getDecoder().decode(settings.vaultKey);
        if (key.length != 32) {
            throw PaymentException.unavailable("Configure the private payment token encryption key.");
        }
        Cipher cipher = Cipher.getInstance("AES/GCM/NoPadding");
        cipher.init(mode, new SecretKeySpec(key, "AES"), new GCMParameterSpec(128, nonce));
        return cipher;
    }
    @Override
    public String encrypt(String token) {
        try {
            byte[] nonce = new byte[12];
            new SecureRandom().nextBytes(nonce);
            byte[] ciphertext = cipher(Cipher.ENCRYPT_MODE, nonce).doFinal(token.getBytes(StandardCharsets.UTF_8));
            return Base64.getEncoder().encodeToString(nonce) + ":" + Base64.getEncoder().encodeToString(ciphertext);
        } catch (GeneralSecurityException | IllegalArgumentException e) {
            throw PaymentException.unavailable("Payment token encryption is unavailable.");
        }
    }
    @Override
    public String decrypt(String encrypted) {
        try {
            String[] parts = encrypted.split(":", -1);
            if (parts.length != 2) {
                throw new IllegalArgumentException();
            }
            byte[] nonce = Base64.getDecoder().decode(parts[0]);
            return new String(cipher(Cipher.DECRYPT_MODE, nonce).doFinal(Base64.getDecoder().decode(parts[1])),
                StandardCharsets.UTF_8);
        } catch (GeneralSecurityException | IllegalArgumentException e) {
            throw PaymentException.unavailable("Payment token could not be decrypted. Check the configured key.");
        }
    }
}
