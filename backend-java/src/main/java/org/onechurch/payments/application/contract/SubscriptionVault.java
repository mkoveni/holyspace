package org.onechurch.payments.application.contract;
public interface SubscriptionVault {
    String encrypt(String token);
    String decrypt(String encrypted);
}
