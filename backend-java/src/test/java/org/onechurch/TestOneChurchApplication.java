package org.onechurch;

import org.onechurch.shared.infrastructure.TestcontainersConfiguration;
import org.springframework.boot.SpringApplication;

public class TestOneChurchApplication {

    public static void main(String[] args) {
        SpringApplication.from(OneChurchApplication::main).with(TestcontainersConfiguration.class).run(args);
    }
}
