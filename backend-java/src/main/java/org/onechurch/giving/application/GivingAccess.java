package org.onechurch.giving.application;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

import java.util.UUID;
public final class GivingAccess {
    private final PermissionChecker auth;
    public GivingAccess(PermissionChecker auth) {
        this.auth = auth;
    }
    public void read() {
        auth.requirePermission("giving.read");
    }
    public void manage(UUID actor) {
        auth.requirePermission(actor, "giving.manage");
    }
    public void reverse(UUID actor) {
        auth.requirePermission(actor, "giving.reverse");
    }
    public void acknowledge(UUID actor) {
        auth.requirePermission(actor, "giving.acknowledge");
    }
    public void reconcile(UUID actor) {
        auth.requirePermission(actor, "giving.reconcile");
    }
    public void export() {
        read();
        auth.requirePermission("giving.export");
    }
}
