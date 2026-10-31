package org.onechurch.identityaccess.infrastructure.bootstrap;

import org.onechurch.identityaccess.application.command.bootstrapadministrator.BootstrapAdministratorCommand;
import org.onechurch.identityaccess.application.command.bootstrapadministrator.BootstrapAdministratorHandler;
import org.springframework.beans.factory.annotation.Value;
import org.springframework.boot.ApplicationArguments;
import org.springframework.boot.ApplicationRunner;
import org.springframework.boot.autoconfigure.condition.ConditionalOnProperty;
import org.springframework.stereotype.Component;

import java.nio.file.Files;
import java.nio.file.Path;

@Component
@ConditionalOnProperty(name = "onechurch.bootstrap.enabled", havingValue = "true")
public final class AdministratorBootstrap implements ApplicationRunner {

    private final BootstrapAdministratorHandler bootstrap;
    private final String username, email, displayName, passwordFile;

    public AdministratorBootstrap(BootstrapAdministratorHandler bootstrap,
        @Value("${onechurch.bootstrap.username:}") String username,
        @Value("${onechurch.bootstrap.email:}") String email,
        @Value("${onechurch.bootstrap.display-name:}") String displayName,
        @Value("${onechurch.bootstrap.password-file:}") String passwordFile) {
        this.bootstrap = bootstrap;
        this.username = username;
        this.email = email;
        this.displayName = displayName;
        this.passwordFile = passwordFile;
    }

    @Override
    public void run(ApplicationArguments arguments) throws Exception {
        if (passwordFile.isBlank()) {
            throw new IllegalArgumentException("A bootstrap password file is required.");
        }
        Path path = Path.of(passwordFile);
        if (Files.size(path) > 4096) {
            throw new IllegalArgumentException("Invalid bootstrap password file.");
        }
        String password = Files.readString(path).replaceFirst("\\r?\\n$", "");
        bootstrap.handle(new BootstrapAdministratorCommand(username, email, displayName, password));
        System.out.println("First administrator created. Disable bootstrap before starting the API.");
    }
}
