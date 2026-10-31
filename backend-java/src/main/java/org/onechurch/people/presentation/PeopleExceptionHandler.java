package org.onechurch.people.presentation;

import org.onechurch.people.domain.PeopleException;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.ExceptionHandler;
import org.springframework.web.bind.annotation.RestControllerAdvice;

import java.util.Map;

@RestControllerAdvice
public class PeopleExceptionHandler {
    @ExceptionHandler(PeopleException.class)
    ResponseEntity<Map<String, String>> people(PeopleException error) {
        int status = switch (error.kind()) {
            case INVALID -> 400;
            case NOT_FOUND -> 404;
            case CONFLICT -> 409;
        };
        return ResponseEntity.status(status).body(Map.of("message", error.getMessage()));
    }
}
