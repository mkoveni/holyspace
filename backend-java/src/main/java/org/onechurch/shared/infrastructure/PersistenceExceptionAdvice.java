package org.onechurch.shared.infrastructure;

import org.jooq.exception.DataAccessException;
import org.springframework.dao.DataIntegrityViolationException;
import org.springframework.dao.DuplicateKeyException;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.ExceptionHandler;
import org.springframework.web.bind.annotation.RestControllerAdvice;

import java.sql.SQLException;
import java.util.Map;

@RestControllerAdvice
public class PersistenceExceptionAdvice {

    @ExceptionHandler(DuplicateKeyException.class)
    ResponseEntity<Map<String, String>> duplicate(DuplicateKeyException error) {
        return response("23505");
    }

    @ExceptionHandler(DataIntegrityViolationException.class)
    ResponseEntity<Map<String, String>> integrity(DataIntegrityViolationException error) {
        Throwable cause = error.getMostSpecificCause();
        return response(cause instanceof SQLException sql ? sql.getSQLState() : null);
    }

    @ExceptionHandler(DataAccessException.class)
    ResponseEntity<Map<String, String>> database(DataAccessException error) {
        return response(error.sqlState());
    }

    private ResponseEntity<Map<String, String>> response(String state) {
        if ("23505".equals(state) || "23P01".equals(state)) {
            return ResponseEntity.status(409)
                .body(Map.of("message", "A duplicate or overlapping record already exists."));
        }
        if ("23503".equals(state)) {
            return ResponseEntity.status(409).body(Map.of("message", "A referenced record is missing or has changed."));
        }
        if ("23514".equals(state) || "23502".equals(state)) {
            return ResponseEntity.badRequest()
                .body(Map.of("message", "The record does not meet the required validation rules."));
        }
        return ResponseEntity.internalServerError().body(Map.of("message", "Unable to complete the request."));
    }
}
