package org.onechurch.communications.presentation;
import org.onechurch.communications.domain.CommunicationException;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.ExceptionHandler;
import org.springframework.web.bind.annotation.RestControllerAdvice;

import java.util.Map;
@RestControllerAdvice
public class CommunicationExceptionHandler {
    @ExceptionHandler(CommunicationException.class)
    ResponseEntity<Map<String, String>> children(CommunicationException error) {
        int status = switch (error.kind()) {
            case INVALID -> 400;
            case NOT_FOUND -> 404;
            case CONFLICT -> 409;
            case FORBIDDEN -> 403;
            case THROTTLED -> 429;
        };
        ResponseEntity.BodyBuilder response = ResponseEntity.status(status);
        if (status == 429) {
            response.header("Retry-After", "900");
        }
        return response.body(Map.of("message", error.getMessage()));
    }
}
