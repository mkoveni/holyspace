package org.onechurch.childrenministry.sessions.application.contract;

public interface PickupTokens {
    String issue();
    String hash(String value);
    boolean matches(String value, String hash);
}
