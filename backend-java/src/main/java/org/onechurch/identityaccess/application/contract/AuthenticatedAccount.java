package org.onechurch.identityaccess.application.contract;
import java.util.UUID;
/** Resolves a trusted caller after checking the live session and account status. */
public interface AuthenticatedAccount {
    UUID requireAccount();
}
