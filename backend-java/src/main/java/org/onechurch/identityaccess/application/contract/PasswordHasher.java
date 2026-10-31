package org.onechurch.identityaccess.application.contract;

public interface PasswordHasher {

    String hash(String password);

    boolean matches(String password, String hash);
}
