package org.onechurch;

import org.onechurch.shared.infrastructure.operations.SchemaMigration;
import org.springframework.boot.SpringApplication;
import org.springframework.boot.autoconfigure.SpringBootApplication;

@SpringBootApplication
public class OneChurchApplication {

    public static void main(String[] args) {
        if (args.length == 1 && "migrate".equals(args[0])) {
            SchemaMigration.run();
            return;
        }
        SpringApplication.run(OneChurchApplication.class, args);
    }
}
