package org.onechurch.events.presentation;

import org.onechurch.events.domain.EventException;
import org.springframework.dao.DuplicateKeyException;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.ExceptionHandler;
import org.springframework.web.bind.annotation.RestControllerAdvice;

import java.util.Map;

@RestControllerAdvice(basePackages = "org.onechurch.events.presentation")
public class EventsExceptionHandler {
    @ExceptionHandler(EventException.class)
    public ResponseEntity<Map<String, String>> failure(EventException e) {
        return ResponseEntity.status(switch (e.kind()) {
            case INVALID -> 400;
            case NOT_FOUND -> 404;
            case CONFLICT -> 409;

        }).body(Map.of("message", e.getMessage()));
    }
    @ExceptionHandler(DuplicateKeyException.class)
    public ResponseEntity<Map<String, String>> duplicate(DuplicateKeyException e) {
        return ResponseEntity.status(409)
            .body(Map.of("message", "This name or request is already in use. Refresh before trying again."));
    }
}
