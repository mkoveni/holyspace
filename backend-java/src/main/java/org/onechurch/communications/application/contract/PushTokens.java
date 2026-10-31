package org.onechurch.communications.application.contract;
public interface PushTokens {
    boolean available();
    String encrypt(String token);
    String decrypt(String encrypted);
}
