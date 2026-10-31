package org.onechurch.identityaccess.infrastructure.security;

import org.onechurch.identityaccess.application.contract.PasswordHasher;
import org.springframework.security.crypto.bcrypt.BCryptPasswordEncoder;
import org.springframework.stereotype.Component;

import java.nio.charset.StandardCharsets;

@Component
public final class BcryptPasswordHasher implements PasswordHasher {

    private final BCryptPasswordEncoder encoder = new BCryptPasswordEncoder(12);

    @Override
    public String hash(String password) {
        return encoder.encode(password);
    }

    @Override
    public boolean matches(String password, String hash) {
        return password != null && password.getBytes(StandardCharsets.UTF_8).length <= 72
            && encoder.matches(password, hash);
    }
}
