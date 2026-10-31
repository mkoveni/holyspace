package org.onechurch.identityaccess.application.contract;

public interface TokenCodec {

    String generate();

    String hash(String token);
}
