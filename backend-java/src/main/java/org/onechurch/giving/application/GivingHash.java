package org.onechurch.giving.application;
import java.nio.charset.StandardCharsets;
import java.security.MessageDigest;
import java.security.NoSuchAlgorithmException;
import java.util.HexFormat;
public final class GivingHash {
    private GivingHash() {
    }
    public static String of(Object... fields) {
        try {
            MessageDigest digest = MessageDigest.getInstance("SHA-256");
            for (Object field : fields) {
                String value = field == null ? "<null>" : field.toString();
                digest.update((value.length() + ":" + value).getBytes(StandardCharsets.UTF_8));
            }
            return HexFormat.of().formatHex(digest.digest());
        } catch (NoSuchAlgorithmException e) {
            throw new IllegalStateException(e);
        }
    }
}
