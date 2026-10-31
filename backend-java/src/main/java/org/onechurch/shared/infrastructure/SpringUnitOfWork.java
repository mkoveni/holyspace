package org.onechurch.shared.infrastructure;

import org.onechurch.shared.application.UnitOfWork;
import org.springframework.stereotype.Component;
import org.springframework.transaction.support.TransactionTemplate;

import java.util.function.Supplier;

@Component
public final class SpringUnitOfWork implements UnitOfWork {

    private final TransactionTemplate transactions;

    public SpringUnitOfWork(org.springframework.transaction.PlatformTransactionManager manager) {
        transactions = new TransactionTemplate(manager);
    }

    @Override
    public <T> T execute(Supplier<T> operation) {
        return transactions.execute(status -> operation.get());
    }
}
