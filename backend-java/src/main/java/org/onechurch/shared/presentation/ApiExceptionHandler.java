package org.onechurch.shared.presentation;

import org.onechurch.identityaccess.domain.IdentityException;
import org.springframework.http.ResponseEntity;
import org.springframework.http.converter.HttpMessageNotReadableException;
import org.springframework.security.access.AccessDeniedException;
import org.springframework.web.bind.MethodArgumentNotValidException;
import org.springframework.web.bind.annotation.ExceptionHandler;
import org.springframework.web.bind.annotation.RestControllerAdvice;
import org.springframework.web.method.annotation.HandlerMethodValidationException;
import org.springframework.web.method.annotation.MethodArgumentTypeMismatchException;

import java.util.Map;

@RestControllerAdvice
public class ApiExceptionHandler {

    @ExceptionHandler(IdentityException.class)
    ResponseEntity<Map<String, String>> identity(IdentityException error) {
        int status = switch (error.kind()) {
            case INVALID -> 400;
            case NOT_FOUND -> 404;
            case CONFLICT -> 409;
            case UNAUTHENTICATED -> 401;
            case FORBIDDEN -> 403;
            case THROTTLED -> 429;
        };
        ResponseEntity.BodyBuilder response = ResponseEntity.status(status);
        if (status == 429) {
            response.header("Retry-After", "60");
        }
        return response.body(Map.of("message", error.getMessage()));
    }

    @ExceptionHandler({MethodArgumentNotValidException.class, HandlerMethodValidationException.class,
        MethodArgumentTypeMismatchException.class, HttpMessageNotReadableException.class})
    ResponseEntity<Map<String, String>> invalid(Exception error) {
        return ResponseEntity.badRequest()
            .body(Map.of("message", "Invalid request. Check the required fields and their formats."));
    }

    @ExceptionHandler(AccessDeniedException.class)
    ResponseEntity<Map<String, String>> forbidden(AccessDeniedException error) {
        return ResponseEntity.status(403).body(Map.of("message", "Permission denied."));
    }
}
