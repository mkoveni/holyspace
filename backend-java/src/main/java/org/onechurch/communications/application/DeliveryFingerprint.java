package org.onechurch.communications.application;
import java.nio.charset.StandardCharsets;
import java.security.MessageDigest;
import java.security.NoSuchAlgorithmException;
import java.util.HexFormat;
public final class DeliveryFingerprint {
    private DeliveryFingerprint() {
    }
    public static String of(Object... values) {
        StringBuilder source = new StringBuilder();
        for (Object value : values) {
            String text = String.valueOf(value);
            source.append(text.length()).append(':').append(text);
        }
        try {
            return HexFormat.of()
                .formatHex(
                    MessageDigest.getInstance("SHA-256").digest(source.toString().getBytes(StandardCharsets.UTF_8)));
        } catch (NoSuchAlgorithmException error) {
            throw new IllegalStateException(error);
        }
    }
}
