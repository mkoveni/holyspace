package org.onechurch.shared.infrastructure.operations;

import org.flywaydb.core.Flyway;

import java.io.IOException;
import java.nio.file.Files;
import java.nio.file.Path;

/** Operator-only entry point. Does not start HTTP, schedulers or application components. */
public final class SchemaMigration {
    private SchemaMigration() {
    }

    public static void run() {
        try {
            String password = Files.readString(Path.of(required("DB_PASSWORD_FILE"))).strip();
            if (password.isEmpty()) {
                throw new IllegalStateException("Migration password file is empty.");
            }
            Flyway.configure()
                .dataSource(required("DB_URL"), required("DB_USERNAME"), password)
                .locations("classpath:db/migration")
                .cleanDisabled(true)
                .baselineOnMigrate(false)
                .load()
                .migrate();
        } catch (IOException error) {
            throw new IllegalStateException("Cannot read migration password file.");
        }
    }

    private static String required(String name) {
        String value = System.getenv(name);
        if (value == null || value.isBlank()) {
            throw new IllegalStateException("Missing migration setting: " + name);
        }
        return value;
    }
}
