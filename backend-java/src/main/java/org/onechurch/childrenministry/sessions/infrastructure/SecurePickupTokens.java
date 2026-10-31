package org.onechurch.childrenministry.sessions.infrastructure;

import org.onechurch.childrenministry.sessions.application.contract.PickupTokens;
import org.springframework.stereotype.Component;

import java.nio.charset.StandardCharsets;
import java.security.MessageDigest;
import java.security.NoSuchAlgorithmException;
import java.security.SecureRandom;
import java.util.Base64;
import java.util.HexFormat;
@Component
public final class SecurePickupTokens implements PickupTokens {
    private final SecureRandom random = new SecureRandom();
    public String issue() {
        byte[] bytes = new byte[16];
        random.nextBytes(bytes);
        return Base64.getUrlEncoder().withoutPadding().encodeToString(bytes);
    }
    public String hash(String value) {
        try {
            return HexFormat.of()
                .formatHex(MessageDigest.getInstance("SHA-256")
                    .digest((value == null ? "" : value).getBytes(StandardCharsets.UTF_8)));
        } catch (NoSuchAlgorithmException error) {
            throw new IllegalStateException(error);
        }
    }
    public boolean matches(String value, String expected) {
        return expected != null && value != null && MessageDigest
            .isEqual(hash(value).getBytes(StandardCharsets.US_ASCII), expected.getBytes(StandardCharsets.US_ASCII));
    }
}
